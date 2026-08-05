# Lahza — what's done and what's left

Status as of 5 August 2026. Digital invitation platform, modelled on da3wa.co
but studio-managed: we build invitations, customers get a link.

---

## How to run it

```bash
# 1. start MySQL in XAMPP
# 2. from the project root:
C:\php84\php.exe artisan serve      # terminal 1
npm run dev                          # terminal 2
```

**Use `C:\php84\php.exe`, not bare `php`** — XAMPP's PHP 8.2 cannot run
Laravel 13 (needs 8.3+, and the pinned Symfony 8 packages need 8.4.1+).

| | |
|---|---|
| Landing | http://127.0.0.1:8000 — coming soon page |
| Studio | http://127.0.0.1:8000/admin |
| Login | `admin@lahza.test` / `password` |
| Live invitation | http://127.0.0.1:8000/i/ahmad-sidra |

```bash
php artisan migrate:fresh --seed   # rebuild: admin user + Lux Wedding + sample invitation
php artisan test                   # 33 tests
vendor/bin/pint                    # formatting
```

Database `lahza` on XAMPP MySQL (MariaDB 10.4), root, no password.

---

## Stack

| Layer | Choice | Note |
|---|---|---|
| Framework | Laravel 13.24 on PHP 8.4 | |
| Frontend | Blade + Alpine + Tailwind **v4** | v4 has no config file — tokens live in `resources/css/app.css` |
| DB | MySQL via XAMPP | MariaDB 10.4 locally; ship MySQL 8.4 |
| Icons | `blade-ui-kit/blade-heroicons` | SVG only, no emoji anywhere |
| Fonts | Inter, self-hosted by the Vite plugin | admin makes no external font request |

Decided against React Native and Next.js. The product is a link pasted into
WhatsApp — an install step between the link and the invitation kills it, and
an app has no URL for WhatsApp to scrape a preview card from.

---

## DONE

### Studio shell

- Admin at `/admin` behind hand-rolled auth. **No registration route** by
  design — accounts come from the seeder or console. A test asserts
  `/register` and `/login` stay 404.
- Login throttles at 5 attempts per email+IP, regenerates the session on
  success, and never reveals whether an email exists.
- Sidebar navigation (not top nav), config-driven from `config/admin.php` —
  adding a section is one entry, not a layout edit.
- Mobile drawer via Alpine.

### Design system

Everything written once, in one place:

- **`resources/css/app.css`** — the entire look. 10 teal brand steps, 10 slate
  neutrals, status colours, two shadows. Change `--color-brand-600` and every
  button, badge, focus ring and active nav item follows.
- **`resources/views/components/ui/`** — 18 components: `button` (4 variants ×
  2 sizes), `card`, `field`, `input`, `select`, `textarea`, `label`, `error`,
  `badge`, `alert`, `table`/`th`/`td`, `stat`, `empty`, `page-header`,
  `nav-item`, `brand`.
- **`resources/views/components/layouts/`** — `admin` (sidebar) and `auth`.

Constraints verified, not assumed: **zero emoji** (scanned every source file by
Unicode range), **zero gradients** in markup or the built CSS.

### Templates

A template pairs a design in code with its data.

- `templates` table: name, slug, category, view, description, intro video,
  music, `palette` JSON, `settings` JSON, is_active.
- **The design's markup lives in code** —
  `resources/views/templates/<view>/show.blade.php`. Adding a design is a
  Blade directory plus a record.
- `TemplateViews` scans that directory, so the form only offers designs that
  exist and a record cannot silently point at a missing file. The index shows
  a red "View missing" badge if one ever does.
- Full CRUD with video/music upload, delete-on-replace, and a remove-music
  toggle. Deleting a template that still has invitations is **refused** rather
  than cascading a customer's page away.
- Categories and locales in `config/templates.php`.

### The Lux Wedding design

Ported from the standalone prototype (still in `archive/lux-wedding/`) into
`resources/views/templates/lux-wedding/show.blade.php`, now fully data-driven.

The sequence: tap → video plays **muted** while music fades up → dissolve
begins **while the video is still moving** → content rises in → music ducks
and goes muffled.

- **Colours sampled from the actual video.** It opens on dark chocolate
  (`#2a201a`) and ends flooded with champagne light (`#c5ab85`), so the reveal
  lands on warm ivory and the final frame flows into the page instead of
  cutting to black.
- **Dissolve timing measured frame by frame.** The clip is 240 frames /
  10.000s; brightness climbs from 7.5s and plateaus at ~9.25s. The crossfade
  starts at 9.2s — box fully open, 95% of peak brightness — with 0.8s of the
  clip still playing.
- **Music goes "foggy"** via a Web Audio low-pass filter (700 Hz, 0.42 gain,
  2.6s ramp). Frequency sweeps exponentially, gain linearly.
- **Sound only ever starts on tap.** Zero `autoplay` in the rendered page,
  asserted by a test.

### Invitations

- `invitations` table: template, slug, locale, Arabic + Latin names, hosts,
  `starts_at` (UTC), timezone, duration, venue, city, map query, phone,
  `programme` JSON, show_verse, published.
- Full CRUD, Alpine programme repeater (blank rows discarded on save),
  publish/unpublish toggle.
- **Draft invitations 404 for guests** but preview for a signed-in studio user.
  404 not 403, so the slug isn't confirmed to someone guessing.
- Public page at `/i/{slug}`, `.ics` at `/i/{slug}/calendar.ics`.
- OG meta tags — WhatsApp is the distribution channel, and a link with no
  preview card looks broken.

### Three languages

Arabic, Sorani Kurdish, English. One invitation is authored in one locale.

- Guest labels in `lang/{ar,ku,en}/invitation.php`. **The admin is English and
  uses no translation keys.**
- **Names stay Arabic in every locale**, with Latin forms shown small beneath
  them on English only.
- **The Quranic verse is never translated** — the Arabic is quoted verbatim in
  all three, and English adds a small translation beneath it.
- Dates and times derive from the single `starts_at` timestamp via ICU, so the
  countdown can never disagree with the printed time.
- Digits follow the locale: `٠١٢٣` for Arabic and Kurdish, `0123` for English.
- Hijri date alongside Gregorian for Arabic and Kurdish only.

### Bugs found and fixed (worth not re-introducing)

- **Letter-spacing on Arabic.** Tracking pulls Arabic and Kurdish letters apart
  and breaks their cursive joins. A `--tk` multiplier is `0` for RTL and `1`
  for Latin. Never apply tracking to Arabic script.
- **Cairo has no Kurdish glyphs.** It's missing exactly `ڕ ڵ ۆ ە ێ`, so the
  browser sourced only those five letters from a fallback face and words
  visibly came apart. Kurdish uses Noto Sans Arabic instead. **Never chain a
  second Arabic font behind an incomplete one** — a Latin fallback degrades
  gracefully, an Arabic one breaks the word.
- **Times were three hours off.** A `datetime` cast is not enough: Eloquent
  stores whatever wall clock the Carbon instance carries, so a zoned instance
  wrote local time into a column read back as UTC. The attribute now converts
  to UTC on write.
- **Hijri dates were Gregorian.** `IntlDateFormatter` *silently ignores* a
  `calendar` locale keyword — `ar@calendar=islamic`, `ar-u-ca-islamic` and
  `TRADITIONAL` all returned `cal=gregorian`. Only an explicit `IntlCalendar`
  passed as the constructor's calendar argument works. Uses **Umm al-Qura**,
  the civil calendar, which differs from the tabular one by a day.
- **Date commas were inverted** — English was getting the Arabic comma `،`.
- Kurdish Hijri months fall back to Arabic: ICU has no Sorani names and emits
  English (`Jumada II`), which reads worse on an Arabic-script page.

### Tests — 33, all passing

- `tests/Feature/Admin/AuthenticationTest.php` — guards, throttling, no
  registration
- `tests/Feature/InvitationPageTest.php` — publish visibility, direction,
  Latin names, no autoplay, OG tags, `.ics` correctness, missing-view failure
- `tests/Unit/InvitationFormattingTest.php` — UTC round-trip, per-locale dates,
  Hijri, programme filtering, map URL fallback

---

## LEFT TO DO

### Next, probably

1. **More templates.** The whole point of the template-as-component structure.
   Each is a new Blade directory + record. Ideas from da3wa: opening door,
   Disney-style castle. Consider **Lottie** — a ring-box animation is ~50KB as
   Lottie versus ~3MB as video, and its colours can be themed from the palette.
2. **Bring back palette extraction from video.** I had a working ffmpeg + GD
   extractor: sample 5 frames, quantise, pick a dark primary that holds white
   text and a vivid accent with ≥4.5 contrast. It scored **chroma** not HSL
   saturation (saturation over-rewards near-black) and excluded candidates near
   the background. Currently colours are entered by hand.
3. **Self-host the Arabic fonts.** The admin's Inter is self-hosted; the
   invitation still pulls Amiri / Aref Ruqaa / Cairo / Noto Sans Arabic from
   bunny.net at runtime.
4. **Get the Kurdish copy checked by a native speaker.** `lang/ku/invitation.php`
   is my best effort and is flagged in the file. The font problem is solved;
   the wording is unverified.

### Known gaps in the design itself

- **Section order is fixed** in the Blade file. No data-driven reordering.
- **One fixed verse.** Christian or secular weddings need a different opening,
  or none. There's an on/off toggle but no alternatives.
- **Countdown is always 4 units.** No "days only" option.
- **One venue, one programme.** Weddings with a separate ceremony and reception
  venue or time can't be expressed.
- **16:9 video is cropped on portrait phones** (`object-fit: cover`). Fine for
  a centred subject; switch to `contain` to letterbox.
- **Hijri can't be turned off** for Arabic/Kurdish.

### Deliberately parked

Discussed and postponed, not forgotten:

- **RSVP** (تأكيد الحضور) — no customer dashboards yet, so no reservations.
  This is the biggest functional gap versus da3wa.
- **Greeting wall** (جدار التهاني) and **memory gallery** — both mean guests
  writing to the database, which needs moderation, rate limiting and spam
  handling on a currently read-only public route.
- **QR entry check-in** — depends on RSVP existing first. If built, start as a
  PWA rather than React Native; a whole second codebase for one door screen is
  poor value.
- **Self-serve + payments.** Da3wa sells tiers (25k/39k/69k IQD). Would need
  customer accounts, per-invitation ownership, authorization policies, and a
  gateway. **Verify gateway availability for your entity before building** —
  Paymob / MyFatoorah / HyperPay / Tap coverage varies, and Iraqi pricing
  implies local rails like ZainCash. Don't assume Stripe is available.

### Before this goes live

- **Media must move to object storage + CDN, never the app server.** This is
  the one decision that's painful to reverse, because it means re-pointing
  invitation links already sent. A 500-guest WhatsApp blast on a 10MB video is
  ~5GB of egress per invitation, in a ten-minute spike:

  | Host | Egress per invitation |
  |---|---|
  | AWS S3 (~$0.09/GB) | **~$0.45** |
  | Cloudflare R2 (zero egress) | **~$0** |
  | Bunny Stream (~$0.005/GB) | **~$0.03** |

  On a ~$19 product, getting this wrong quietly eats the margin. Bunny Stream
  for video (transcode + HLS + MENA edge), R2 for audio and images.
- **Move media upload and any transcoding to a queue.** Uploads are handled
  synchronously in the request — fine for one user.
- **Full-page cache** the guest invitation, keyed on `invitation + updated_at`.
  It's identical for every guest and absorbs the traffic spike.
- **Redis** for cache, session and queue in production.
- **PHP 8.4+ and MySQL 8.4** on the server; not MariaDB 10.4, not Windows.

---

## Local environment notes

- **`C:\php84\php.exe`** is PHP 8.4.23 with `pdo_mysql`, `gd`, `intl`, `zip`,
  `mbstring`. XAMPP's 8.2 is too old for Laravel 13.
- **PHP upload limits were raised** to `upload_max_filesize 256M`,
  `post_max_size 300M`, `memory_limit 512M` — in
  `C:\Users\ahmad\scoop\apps\php\current\cli\php.ini`, which *overrides*
  `C:\php84\php.ini`. A `scoop update php` will reset it, and since it lives
  outside the repo anyone else cloning needs the same change.
- **ffmpeg 8.0.1** is installed and was used to measure the video's brightness
  curve. Needed again if palette extraction returns.
- **Don't test Arabic through the Windows console.** Command-line arguments get
  mangled to `?` before reaching PHP, which looks exactly like a database
  encoding bug. It isn't — MySQL and the app round-trip `utf8mb4` correctly.
  Verify through the app or a UTF-8 source file.
