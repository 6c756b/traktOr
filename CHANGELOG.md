# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.1] - 2026-07-20

### Added

- Confirmation dialog before marking a Continue Watching episode as watched
- Short intro sentence on the login page explaining what TraktOr is, above the existing tagline

### Changed

- Continue Watching cards: the "+N new episodes" badge and "mark as watched" button are now combined
  into a single two-row chip overlaid on the poster (count on top, checkmark below), replacing the
  separate corner badge and full-width button underneath
- Login page's GitHub link now uses the official GitHub mark instead of a generic arrow icon

## [0.4.0] - 2026-07-19

### Added

- Discover tab on the Search page — Recommended Shows, Recommended Movies, Trending Shows and Popular Movies, shown when the search field is empty and cached per tab for the session
- "More like this" section on show and movie detail pages, backed by Trakt's related-items data
- Show/movie detail pages and Search/Discover results now work for titles never synced from your Trakt account before — details are fetched live on demand, and starting to watch a previewed title syncs it automatically instead of requiring a prior full sync
- Marking an episode or season as watched that skips over earlier unwatched episodes now asks whether those should be marked watched too, instead of silently leaving gaps in watch history
- Search and Discover result cards link through to the show/movie detail page and reflect actual watchlist status instead of always showing "not on watchlist"
- Watchlist toggle on the movie detail page (shows already had one)
- Connected Trakt account info (avatar, name, username, member since) shown in Settings under the connection status
- Trakt Collection tracking — mark movies and individual show seasons as owned, with toggles on the movie detail page and in the episode list, synced to Trakt's native Collection feature
- "In collection" badge on Library/Watchlist cards and a "Collection only" filter; the badge turns orange once you've caught up on watching a show but a newer season isn't in your collection yet
- Private per-title notes, visible only to you and never synced to Trakt (Trakt's own Notes feature requires VIP) — edited via a "…" menu on the movie and show detail pages, shown under the title when set

### Changed

- "More like this" section on detail pages now reads "Similar movies"/"Similar shows" and is visually separated from the rest of the page instead of running straight into it
- Season "mark watched"/"collection" controls in the episode list are compact, colored, icon-labeled buttons instead of full-width plain ones, and the "mark watched" button hides once a season is already fully watched

### Fixed

- Poster badges (e.g. "Already watched") on Library and Show cards were briefly covered by the poster's own hover effect due to a CSS stacking-context issue
- Library/Watchlist cards switched to a squeezed side-by-side poster+text layout before the grid had actually dropped to a single column, leaving titles unreadable in a narrow range of screen widths

## [0.3.0] - 2026-07-18

### Added

- Search — find any show or movie on trakt.tv from inside TraktOr and either add it to the watchlist or start watching immediately (marks episode 1 watched for shows, marks the movie watched for films), even for titles never synced from your Trakt account before
- "Mark as watched" action on the movie detail page — previously the only way a movie's watched status could change was through a full Trakt sync
- Search results already fully watched show an "Already watched" badge instead of action buttons, and use a localized title/description matching your selected UI language where a TMDB translation is available

### Fixed

- Adding an item to the watchlist that wasn't already synced from Trakt (e.g. a fresh search result) wrote a watchlist entry with no matching show/movie data behind it, leaving it undetailable — it's now synced first

## [0.2.0] - 2026-07-18

### Added

- Watchlist view — new nav tab tracking Trakt's watchlist independently from watch history, with the same search/filter/sort tools as Library, plus add/remove actions (with a confirmation dialog) from both the grid and the show detail page
- "Cancel show" — drop a show from Continue Watching without touching its watch history, with a one-click resume; lives in a new dropdown menu on the show detail page that also links out to trakt.tv/TMDB
- Full mobile UI pass: icon-based navigation, horizontal card layout for shows/movies, a collapsible filter bar, a scroll-to-top button, and numerous responsive fixes across Continue Watching, Library, Watchlist and the Show/Movie detail pages

### Changed

- Episode lists now load in two independent, non-blocking requests (a cacheable season/episode shape plus always-live watched progress) instead of one slow blocking call
- The manual/nightly sync summary now also reports watchlist and canceled-show counts

### Fixed

- Search filters no longer misinterpret literal `%`/`_` characters in a search term as SQL wildcards
- An expired session now redirects to login automatically instead of surfacing raw errors from every affected action
- Login/logout no longer misreport success as failure after a network hiccup
- Server timestamps were parsed in the browser's local timezone instead of UTC, drifting "last watched" times and air dates
- Star ratings roll back and show an error toast if saving to Trakt fails
- A failed Trakt OAuth connection attempt is now surfaced with a message instead of failing silently
- Headings that wrap to multiple lines no longer overlap the line below

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
