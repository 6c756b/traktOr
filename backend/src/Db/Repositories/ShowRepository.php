<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;
use TraktOr\Db\FilterQueryBuilder;

final class ShowRepository
{
    private const SORT_COLUMNS = [
        'title' => ['column' => 's.title', 'defaultDir' => 'ASC'],
        'year' => ['column' => 's.year', 'defaultDir' => 'DESC'],
        'rating' => ['column' => 'r.rating', 'defaultDir' => 'DESC'],
        'added' => ['column' => 'sp.last_watched_at', 'defaultDir' => 'DESC'],
        'listed' => ['column' => 'wl.listed_at', 'defaultDir' => 'DESC'],
    ];

    private const SELECT_COLUMNS = "SELECT
            s.trakt_id, s.slug, s.title, s.year, s.overview, s.status, s.network, s.runtime,
            s.genres, s.poster_url, s.backdrop_url, s.aired_episodes, s.certification, s.translations,
            s.raw_json->>'$.ids.tmdb' AS tmdb_id,
            sp.aired, sp.completed, sp.last_watched_at, sp.hidden,
            sp.last_episode_season, sp.last_episode_number,
            sp.next_episode_season, sp.next_episode_number, sp.next_episode_title, sp.next_episode_first_aired,
            wl.listed_at AS watchlist_listed_at,
            r.rating
        FROM shows s";

    // findOne() always uses this -- a show must be reachable by its detail page regardless
    // of watched status (e.g. clicked from the Watchlist), so it never filters on progress.
    // watchlist_items is always LEFT JOINed (like ratings) so "is this on my watchlist" is
    // generically available, not just as a filter -- search()'s watchlist_only mode below
    // turns it into a filter via a WHERE condition instead of a second, redundant JOIN.
    private const JOIN_LEFT = "
        LEFT JOIN show_progress sp ON sp.trakt_show_id = s.trakt_id
        LEFT JOIN watchlist_items wl ON wl.item_type = 'show' AND wl.item_trakt_id = s.trakt_id
        LEFT JOIN ratings r ON r.item_type = 'show' AND r.trakt_id = s.trakt_id";

    // search() uses this by default (the library listing) -- "watched" is defined as
    // "has a show_progress row", which is only ever created by syncWatchedShows()/syncShow().
    private const JOIN_INNER = "
        JOIN show_progress sp ON sp.trakt_show_id = s.trakt_id
        LEFT JOIN watchlist_items wl ON wl.item_type = 'show' AND wl.item_trakt_id = s.trakt_id
        LEFT JOIN ratings r ON r.item_type = 'show' AND r.trakt_id = s.trakt_id";

    public function upsert(array $row): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO shows (
                trakt_id, slug, title, year, overview, status, network, runtime,
                first_aired, genres, poster_url, backdrop_url, aired_episodes, certification, raw_json
            ) VALUES (
                :trakt_id, :slug, :title, :year, :overview, :status, :network, :runtime,
                :first_aired, :genres, :poster_url, :backdrop_url, :aired_episodes, :certification, :raw_json
            )
            ON DUPLICATE KEY UPDATE
                slug = VALUES(slug), title = VALUES(title), year = VALUES(year),
                overview = VALUES(overview), status = VALUES(status), network = VALUES(network),
                runtime = VALUES(runtime), first_aired = VALUES(first_aired), genres = VALUES(genres),
                poster_url = COALESCE(VALUES(poster_url), poster_url),
                backdrop_url = COALESCE(VALUES(backdrop_url), backdrop_url),
                aired_episodes = VALUES(aired_episodes), certification = VALUES(certification),
                raw_json = VALUES(raw_json)'
        );
        $stmt->execute($row);
    }

    public function updateImages(int $traktId, ?string $posterUrl, ?string $backdropUrl): void
    {
        Database::pdo()->prepare(
            'UPDATE shows SET
                poster_url = COALESCE(:poster_url, poster_url),
                backdrop_url = COALESCE(:backdrop_url, backdrop_url)
             WHERE trakt_id = :id'
        )->execute(['poster_url' => $posterUrl, 'backdrop_url' => $backdropUrl, 'id' => $traktId]);
    }

    /** Merges one language into the translations JSON column, without overwriting other languages. */
    public function mergeTranslation(int $traktId, string $language, ?string $title, ?string $overview): void
    {
        if ($title === null && $overview === null) {
            return;
        }
        $fragment = json_encode([$language => array_filter([
            'title' => $title,
            'overview' => $overview,
        ], fn ($v) => $v !== null)]);

        Database::pdo()->prepare(
            'UPDATE shows SET translations = JSON_MERGE_PATCH(COALESCE(translations, "{}"), :fragment) WHERE trakt_id = :id'
        )->execute(['fragment' => $fragment, 'id' => $traktId]);
    }

    /** @return array<int, array<int, string>> [season => [episode number => title]] for one language */
    public function getEpisodeTranslations(int $traktId, string $language): array
    {
        $stmt = Database::pdo()->prepare('SELECT episode_translations FROM shows WHERE trakt_id = :id');
        $stmt->execute(['id' => $traktId]);
        $json = $stmt->fetchColumn();
        $all = $json ? (json_decode($json, true) ?? []) : [];
        return $all[$language] ?? [];
    }

    /**
     * Merges episode titles for one language into the episode_translations JSON column,
     * without overwriting other languages or already-cached seasons.
     *
     * @param array<int, array<int, string>> $titlesBySeasonAndEpisode
     */
    public function mergeEpisodeTranslations(int $traktId, string $language, array $titlesBySeasonAndEpisode): void
    {
        if ($titlesBySeasonAndEpisode === []) {
            return;
        }
        $fragment = json_encode([$language => $titlesBySeasonAndEpisode]);
        Database::pdo()->prepare(
            'UPDATE shows SET episode_translations = JSON_MERGE_PATCH(COALESCE(episode_translations, "{}"), :fragment) WHERE trakt_id = :id'
        )->execute(['fragment' => $fragment, 'id' => $traktId]);
    }

    /** Language-independent tmdb id for TMDB episode-title lookups, read from the show's
     *  cached raw Trakt payload (populated on every sync path, see SyncService::mapShow())
     *  -- avoids a dedicated live GET /shows/:id Trakt call just to extract one field. */
    public function getTmdbId(int $traktId): ?int
    {
        $stmt = Database::pdo()->prepare('SELECT raw_json FROM shows WHERE trakt_id = :id');
        $stmt->execute(['id' => $traktId]);
        $json = $stmt->fetchColumn();
        $raw = $json ? json_decode($json, true) : null;
        return $raw['ids']['tmdb'] ?? null;
    }

    /** @return array{airedEpisodes: ?int, seasons: array<int, array{number:int, year:?int, episodeNumbers:int[]}>}|null */
    public function getSeasonStructure(int $traktId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT season_structure FROM shows WHERE trakt_id = :id');
        $stmt->execute(['id' => $traktId]);
        $json = $stmt->fetchColumn();
        return $json ? json_decode($json, true) : null;
    }

    public function updateSeasonStructure(int $traktId, array $structure): void
    {
        Database::pdo()->prepare(
            'UPDATE shows SET season_structure = :structure WHERE trakt_id = :id'
        )->execute(['structure' => json_encode($structure), 'id' => $traktId]);
    }

    /** @return int[] */
    public function allTraktIds(): array
    {
        return array_map('intval', Database::pdo()->query('SELECT trakt_id FROM shows')->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function search(array $filters, string $sort = 'title', string $dir = '', string $language = 'en'): array
    {
        [$where, $params] = FilterQueryBuilder::build($filters, 's');
        $watchlistOnly = !empty($filters['watchlist_only']);
        $joins = $watchlistOnly ? self::JOIN_LEFT : self::JOIN_INNER;

        if (isset($filters['rating_min'])) {
            $where[] = 'r.rating >= :rating_min';
            $params['rating_min'] = $filters['rating_min'];
        }

        if (isset($filters['list_id'])) {
            $joins .= " JOIN list_items li ON li.item_type = 'show' AND li.item_trakt_id = s.trakt_id";
            $where[] = 'li.list_id = :list_id';
            $params['list_id'] = $filters['list_id'];
        }

        if ($watchlistOnly) {
            $where[] = 'wl.listed_at IS NOT NULL';
        }

        // 'listed' only resolves to a real column when watchlist_items is actually joined --
        // otherwise fall back to the default sort, same as any other unrecognized sort key.
        $sortKey = $sort === 'listed' && !$watchlistOnly ? 'title' : $sort;
        $sortDef = self::SORT_COLUMNS[$sortKey] ?? self::SORT_COLUMNS['title'];
        $direction = strtoupper($dir) === 'ASC' || strtoupper($dir) === 'DESC' ? strtoupper($dir) : $sortDef['defaultDir'];

        $sql = self::SELECT_COLUMNS . $joins
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY {$sortDef['column']} {$direction}";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return array_map(fn ($row) => self::mapRow($row, $language), $stmt->fetchAll());
    }

    public function findOne(int $traktId, string $language = 'en'): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT_COLUMNS . self::JOIN_LEFT . ' WHERE s.trakt_id = :id');
        $stmt->execute(['id' => $traktId]);
        $row = $stmt->fetch();
        return $row ? self::mapRow($row, $language) : null;
    }

    /** @return string[] */
    public function distinctGenres(bool $watchlistOnly = false): array
    {
        $sql = $watchlistOnly
            ? "SELECT s.genres FROM shows s JOIN watchlist_items wi ON wi.item_type = 'show' AND wi.item_trakt_id = s.trakt_id"
            : 'SELECT s.genres FROM shows s JOIN show_progress sp ON sp.trakt_show_id = s.trakt_id';
        $raw = Database::pdo()->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
        return FilterQueryBuilder::distinctGenres($raw);
    }

    private static function mapRow(array $row, string $language = 'en'): array
    {
        $translations = json_decode($row['translations'] ?? '{}', true) ?? [];
        $translation = $translations[$language] ?? [];

        return [
            'id' => (int) $row['trakt_id'],
            'slug' => $row['slug'],
            'title' => $translation['title'] ?? $row['title'],
            'year' => $row['year'] !== null ? (int) $row['year'] : null,
            'overview' => $translation['overview'] ?? $row['overview'],
            'status' => $row['status'],
            'network' => $row['network'],
            'runtime' => $row['runtime'] !== null ? (int) $row['runtime'] : null,
            'genres' => json_decode($row['genres'] ?? '[]', true) ?? [],
            'posterUrl' => $row['poster_url'],
            'backdropUrl' => $row['backdrop_url'],
            'airedEpisodes' => $row['aired_episodes'] !== null ? (int) $row['aired_episodes'] : null,
            'certification' => $row['certification'],
            'rating' => $row['rating'] !== null ? (int) $row['rating'] : null,
            'tmdbId' => $row['tmdb_id'] !== null ? (int) $row['tmdb_id'] : null,
            'onWatchlist' => $row['watchlist_listed_at'] !== null,
            'progress' => $row['aired'] !== null ? [
                'aired' => (int) $row['aired'],
                'completed' => (int) $row['completed'],
                'lastWatchedAt' => $row['last_watched_at'],
                'hidden' => (bool) $row['hidden'],
                'lastEpisode' => $row['last_episode_season'] !== null ? [
                    'season' => (int) $row['last_episode_season'],
                    'number' => (int) $row['last_episode_number'],
                ] : null,
                'nextEpisode' => $row['next_episode_season'] !== null ? [
                    'season' => (int) $row['next_episode_season'],
                    'number' => (int) $row['next_episode_number'],
                    'title' => $row['next_episode_title'],
                    'firstAired' => $row['next_episode_first_aired'],
                ] : null,
            ] : null,
        ];
    }
}
