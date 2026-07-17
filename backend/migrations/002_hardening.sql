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
