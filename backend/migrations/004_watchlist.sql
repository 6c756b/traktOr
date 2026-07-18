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
