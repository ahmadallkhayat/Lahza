# Changelog

All notable changes to Lahza are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Add entries under **Unreleased** as you work. When you cut a release, run
`php artisan version:bump patch|minor|major` — it moves everything under
Unreleased into a dated section and raises the number in `VERSION`.

## [Unreleased]

## [0.1.0] - 2026-08-06

First tagged version. Everything below already existed; this is the point
the project started being versioned.

### Added

- Studio admin behind `/admin`. Sign-in is by username, rate limited to five
  attempts per username and IP, with no public registration route.
- Account settings for the studio's own name, username, email and password.
  Changing the password ends every other signed-in session.
- Templates built on a base template, each with a colour palette, playback
  timings, an intro video and background music.
- Colour palettes derived automatically from an uploaded intro video, sampling
  frames with ffmpeg and quantising them with GD. Falls back to the colours
  typed into the form when the binaries are absent.
- Invitations in Arabic, Sorani Kurdish and English, one content card per
  language, with Baghdad as the default timezone.
- Translate-from-English for the other languages, including a keyless Kurdish
  path, rejecting output returned in the wrong script.
- Public invitation pages at `/i/{slug}` with a per-language URL so one link
  can be sent per language, plus a `calendar.ics` download.
- Self-hosted Inter fonts, so an invitation makes no request to an external
  font host at runtime.
- Automatic deployment to production on every push to master: atomic releases
  built into `releases/<sha>`, activated only on success, with one-command
  rollback.

[Unreleased]: https://github.com/ahmadallkhayat/Lahza/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ahmadallkhayat/Lahza/releases/tag/v0.1.0
