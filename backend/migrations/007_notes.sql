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
