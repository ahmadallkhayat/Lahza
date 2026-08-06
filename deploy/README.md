# Deployment

Production runs at <https://lahza-invites.click> on `68.168.20.31`.
Pushing or merging to `master` deploys automatically via
[`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml).

## Layout on the server

```
/var/www/lahza/
├── repo/                     bare-ish clone, fetched each deploy
├── shared/                   survives every deploy
│   ├── .env                  real production config (never in git)
│   └── storage/              uploads, logs, framework caches
├── releases/
│   ├── 20260806092152-c72a102/
│   └── …                     last 5 kept
└── current -> releases/…     atomically swapped symlink
```

`nginx` serves `current/public`, so activating a release is a single
symlink rename — requests see either the old release or the new one,
never a half-built tree.

## What a deploy does

1. Fetch `origin/master`.
2. Install any packages listed in `packages.txt` that are missing.
3. Extract the commit into a fresh `releases/<ts>-<sha>` directory.
4. Link `shared/.env` and `shared/storage` into it.
5. Append any new keys from `.env.example` to the live `.env`.
6. `composer install --no-dev`, `npm ci`, `npm run build`.
7. `php artisan migrate --force`, then warm config/route/view/event caches.
8. **Only if all of the above succeeded**, swap `current` and reload PHP-FPM.
9. Restart the queue worker, prune old releases.

A failure anywhere in steps 1–7 discards the half-built release and leaves
the running site completely untouched.

## Adding a dependency

| Need | What to do |
|------|------------|
| PHP package | `composer require …`, commit `composer.json` + `composer.lock` |
| JS package | `npm install …`, commit `package.json` + `package-lock.json` |
| System package or PHP extension | add a line to [`packages.txt`](packages.txt) |
| New config value | add it to `.env.example` — the deploy appends it to the live `.env` with the example's value, then set the real value on the server |

## Manual commands (on the server, as `deploy`)

```bash
lahza-deploy              # deploy origin/master now
lahza-rollback --list     # show releases, * marks the live one
lahza-rollback            # go back one release
lahza-rollback <name>     # go to a specific release
```

Rollback swaps code only — **database migrations are not reverted**. Write
migrations so the previous release can still run against the new schema
(add columns, don't rename or drop in the same deploy as the code change).

## Upload limits

Uploads are unlimited, which takes three layers agreeing. Raising one alone
does nothing — the smallest wins, and PHP/nginx reject an oversized body
before Laravel can turn it into a readable validation message.

| Layer | Setting | Value |
|-------|---------|-------|
| nginx | `client_max_body_size` | `0` (checking disabled) |
| PHP-FPM | `post_max_size` | `0` (limit disabled) |
| PHP-FPM | `upload_max_filesize` | `10G` (no unlimited value exists) |
| App | `templates.max_video_kb` / `max_music_kb` | `null` |

The PHP side lives in `/etc/php/8.5/fpm/conf.d/99-lahza.ini` — a separate
file so a `php8.5-fpm` package upgrade cannot revert it. nginx's is in
`/etc/nginx/sites-available/lahza`, together with the 3600s FastCGI timeouts
that a long upload plus ffmpeg palette extraction needs.

**Neither is in this repo**, so a rebuilt server starts from the distro
defaults (`post_max_size 8M`, `upload_max_filesize 2M`) and uploads over 8MB
fail with a bare 413.

## Secrets

`shared/.env` is the only copy of production config and is not in git.
GitHub Actions holds `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_USER` and
`SSH_KNOWN_HOSTS`. The CI key is command-restricted on the server: it can
only run `lahza-deploy`, with no shell, pty, or forwarding.
