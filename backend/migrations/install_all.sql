-- ============================================================================
-- TraktOr -- combined schema for a FRESH install (phpMyAdmin-friendly).
--
-- This file is a straight, in-order concatenation of every 00N_*.sql migration
-- in this directory. Import THIS ONE FILE in one go for a brand new, empty
-- database -- no need to run 001, 002, 003... one by one via a shell.
--
-- Already have an existing install? Do NOT run this file -- it would re-run
-- every CREATE TABLE from scratch. Apply only the individual 00N_*.sql files
-- you haven't applied yet (check which tables/columns you already have).
--
-- GENERATED FILE: this is a mechanical concatenation, not hand-maintained.
-- Whenever a new 00N_*.sql migration is added, regenerate this file (keep this
-- header, replace everything below it) with the individual files in numeric
-- order -- or just ask Claude to redo it.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 001_init.sql
-- ---------------------------------------------------------------------------

-- TraktOr initial schema
-- Single-user app: trakt_tokens/app_settings are singleton tables (always id=1).
-- The app password lives in config.php (app.password_hash), not in the DB --
-- a single static value doesn't need its own table.

CREATE TABLE app_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    -- ISO 639-1 code, e.g. 'en', 'de', 'es'. 'en' is always available (Trakt's original language),
    -- other languages are fetched on demand via TMDB (see shows.translations).
    language VARCHAR(5) NOT NULL DEFAULT 'en',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_app_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE trakt_tokens (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    access_token VARCHAR(255) NOT NULL,
    refresh_token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    scope VARCHAR(100) NULL,
    connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_trakt_tokens_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sync_state (
    resource VARCHAR(50) NOT NULL PRIMARY KEY,
    status ENUM('idle', 'running', 'error') NOT NULL DEFAULT 'idle',
    last_synced_at DATETIME NULL,
    last_error TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shows (
    trakt_id INT UNSIGNED NOT NULL PRIMARY KEY,
    slug VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    year SMALLINT UNSIGNED NULL,
    overview TEXT NULL,
    status VARCHAR(50) NULL,
    network VARCHAR(150) NULL,
    runtime SMALLINT UNSIGNED NULL,
    first_aired DATETIME NULL,
    genres JSON NULL,
    poster_url VARCHAR(500) NULL,
    backdrop_url VARCHAR(500) NULL,
    aired_episodes SMALLINT UNSIGNED NULL,
    certification VARCHAR(20) NULL,
    -- title/overview are always Trakt's original text (English) -- guaranteed fallback.
    -- translations holds additional languages: {"de": {"title": "...", "overview": "..."}, ...}
    -- Filled lazily: only languages that have been active at some point in app_settings.language.
    translations JSON NULL,
    raw_json JSON NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shows_status (status),
    INDEX idx_shows_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE movies (
    trakt_id INT UNSIGNED NOT NULL PRIMARY KEY,
    slug VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    year SMALLINT UNSIGNED NULL,
    overview TEXT NULL,
    status VARCHAR(50) NULL,
    genres JSON NULL,
    poster_url VARCHAR(500) NULL,
    backdrop_url VARCHAR(500) NULL,
    runtime SMALLINT UNSIGNED NULL,
    released DATE NULL,
    certification VARCHAR(20) NULL,
    translations JSON NULL,
    raw_json JSON NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_movies_status (status),
    INDEX idx_movies_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE show_progress (
    trakt_show_id INT UNSIGNED NOT NULL PRIMARY KEY,
    aired SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    completed SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_watched_at DATETIME NULL,
    last_episode_season SMALLINT UNSIGNED NULL,
    last_episode_number SMALLINT UNSIGNED NULL,
    next_episode_season SMALLINT UNSIGNED NULL,
    next_episode_number SMALLINT UNSIGNED NULL,
    next_episode_title VARCHAR(255) NULL,
    next_episode_first_aired DATETIME NULL,
    reset_at DATETIME NULL,
    hidden TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_show_progress_show FOREIGN KEY (trakt_show_id) REFERENCES shows (trakt_id) ON DELETE CASCADE,
    INDEX idx_show_progress_weiterschauen (completed, aired, next_episode_first_aired)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ratings (
    item_type ENUM('show', 'movie', 'episode') NOT NULL,
    trakt_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    rated_at DATETIME NOT NULL,
    PRIMARY KEY (item_type, trakt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lists (
    trakt_list_id INT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE list_items (
    list_id INT UNSIGNED NOT NULL,
    item_type ENUM('show', 'movie') NOT NULL,
    item_trakt_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (list_id, item_type, item_trakt_id),
    CONSTRAINT fk_list_items_list FOREIGN KEY (list_id) REFERENCES lists (trakt_list_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 002_hardening.sql
-- ---------------------------------------------------------------------------

-- TraktOr hardening migration (review after phase 5, see tasks/improvements.md)

-- Login rate limiting: IP-based, survives cookie clearing unlike a
-- session counter. Single-user client only, row count stays trivially small --
-- no cleanup cronjob needed.
CREATE TABLE login_attempts (
    ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Visibility for shows silently skipped during /sync/full (see
-- SyncService::syncWatchedShows() -- shows whose metadata call fails get
-- skipped instead of blowing up the entire sync with an exception, but were previously
-- not visible anywhere).
ALTER TABLE sync_state ADD COLUMN last_warning TEXT NULL AFTER last_error;

-- Episode title cache (analogous to the existing translations field): this way, TMDB season
-- queries for the episode list only need to happen once per language instead of on every
-- page load. Structure: {"de": {"3": {"7": "Title"}, ...}, ...} (language -> season -> episode).
ALTER TABLE shows ADD COLUMN episode_translations JSON NULL AFTER translations;

-- ---------------------------------------------------------------------------
-- 003_theme.sql
-- ---------------------------------------------------------------------------

-- Dark/light mode preference. NULL means "follow the OS setting" (the CSS
-- @media (prefers-color-scheme: dark) default) -- only set once the user actually
-- flips the switch in Settings.
ALTER TABLE app_settings ADD COLUMN theme ENUM('light', 'dark') NULL DEFAULT NULL AFTER language;

-- ---------------------------------------------------------------------------
-- 004_watchlist.sql
-- ---------------------------------------------------------------------------

-- Watchlist: Trakt items marked "to watch" but not yet started (GET /sync/watchlist).
-- Distinct from `lists`/`list_items` (Trakt's custom/personal lists) -- unrelated concept.
-- No FK to shows/movies (same reasoning as list_items): rows are written in the same sync
-- pass that upserts the referenced shows/movies row, no ordering dependency needed.
CREATE TABLE watchlist_items (
    item_type ENUM('show', 'movie') NOT NULL,
    item_trakt_id INT UNSIGNED NOT NULL,
    listed_at DATETIME NOT NULL,
    PRIMARY KEY (item_type, item_trakt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Watched marker for movies, mirrors what show_progress's presence already means for shows.
-- Needed once the watchlist sync starts upserting not-yet-watched movies into this table too --
-- without this column, "row exists in movies" no longer reliably means "watched".
ALTER TABLE movies ADD COLUMN watched_at DATETIME NULL AFTER released;
ALTER TABLE movies ADD INDEX idx_movies_watched_at (watched_at);

-- Backfill: every existing row in `movies` was inserted exclusively through
-- syncWatchedMovies(), so every existing row IS watched -- the precise per-movie timestamp
-- was previously discarded and can't be recovered, but the next full sync overwrites this
-- with the real Trakt value anyway. `updated_at` is just a safe non-null placeholder so
-- movies don't transiently vanish from the library between this migration and the next sync.
UPDATE movies SET watched_at = updated_at WHERE watched_at IS NULL;

-- ---------------------------------------------------------------------------
-- 005_season_structure.sql
-- ---------------------------------------------------------------------------

-- Structural episode-list cache (season/episode numbers + premiere year), decoupled from the
-- live per-episode watched-progress fetch (see SyncService::getSeasonShape()/getProgress()).
-- Deliberately does NOT store titles -- those stay in the existing per-language
-- episode_translations cache; this only defines "which episodes exist" + year, both of which
-- are language-independent and only change when Trakt adds new seasons/episodes.
-- Structure: {"airedEpisodes": 24, "seasons": [{"number":1,"year":2020,"episodeNumbers":[1,2,...]}]}
-- Never contains season 0 (specials) -- matches the old behavior where the episode list was
-- sourced from /progress/watched, which excludes specials by default.
-- "airedEpisodes" is a snapshot of Trakt's live progress "aired" count at build time, used as
-- a cheap staleness check in SyncService::getProgress() to detect newly aired episodes.
ALTER TABLE shows ADD COLUMN season_structure JSON NULL AFTER episode_translations;

-- ---------------------------------------------------------------------------
-- 006_collection.sql
-- ---------------------------------------------------------------------------

-- Collection: items the user owns (physically/digitally), tracked via Trakt's native
-- Collection concept (GET/POST /sync/collection) -- distinct from Watchlist ("to watch")
-- and watched_at/show_progress ("have watched"). Movies use season_number = 0 as a
-- "whole item" sentinel (MySQL PK columns can't be NULL); shows are tracked per season
-- since this app only offers a whole-season toggle, mirroring markSeasonWatched()'s
-- season-shorthand precedent. No FK to shows/movies, same reasoning as watchlist_items:
-- rows are written in the same sync pass that upserts the referenced show/movie.
CREATE TABLE collection_items (
    item_type ENUM('show', 'movie') NOT NULL,
    item_trakt_id INT UNSIGNED NOT NULL,
    season_number SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    collected_at DATETIME NOT NULL,
    PRIMARY KEY (item_type, item_trakt_id, season_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 007_notes.sql
-- ---------------------------------------------------------------------------

-- Private local notes on movies/shows -- pure local data, deliberately never synced to/from
-- Trakt (Trakt's own "Notes" feature is VIP-only, which this app's user doesn't have/want).
-- Structurally mirrors `ratings` (same item_type/trakt_id PK shape), but unlike every other
-- table in this schema, nothing ever writes here via TraktClient -- this is the first
-- purely-local, non-synced per-item table.
CREATE TABLE notes (
    item_type ENUM('show', 'movie') NOT NULL,
    trakt_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_type, trakt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
