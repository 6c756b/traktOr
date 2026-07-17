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
