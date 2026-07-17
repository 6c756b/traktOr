# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned

- Add new shows/movies directly from TraktOr (Trakt search + add to library), instead of  only tracking what's already in your Trakt account
- Mobile UI overhaul

## [0.1.0] - 2026-07-18

Initial release.

### Added

- Continue Watching view with next-episode tracking and sort options
- Library with search, genre/status/rating filters and detail pages for shows and movies
- Episode list per show with per-episode and per-season "mark as watched" (cascades to  earlier unwatched episodes in the same season)
- Star ratings for shows and movies, synced to Trakt
- Trakt OAuth connection and manual "sync now"
- Nightly sync via a self-gating cron dispatcher, safe to run on a single shared-hosting cron slot
- Multi-language UI (German, English), auto-discovered from `frontend/src/lib/i18n/locales/` so additional languages can be added without touching shared code
- Episode/season title translations fetched from TMDB and cached per language
- Light/dark mode, following the OS setting by default with a manual override in Settings
- Generic subpath hosting (deployable at a domain root or any subfolder without code changes)
- Password-protected single-user login with rate limiting
