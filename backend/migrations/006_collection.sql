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
