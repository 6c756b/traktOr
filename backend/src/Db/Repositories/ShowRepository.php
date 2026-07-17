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
    ];

    private const SELECT = "SELECT
            s.trakt_id, s.slug, s.title, s.year, s.overview, s.status, s.network, s.runtime,
            s.genres, s.poster_url, s.backdrop_url, s.aired_episodes, s.certification, s.translations,
            sp.aired, sp.completed, sp.last_watched_at,
            sp.last_episode_season, sp.last_episode_number,
            sp.next_episode_season, sp.next_episode_number, sp.next_episode_title, sp.next_episode_first_aired,
            r.rating
        FROM shows s
        LEFT JOIN show_progress sp ON sp.trakt_show_id = s.trakt_id
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

    /** @return int[] */
    public function allTraktIds(): array
    {
        return array_map('intval', Database::pdo()->query('SELECT trakt_id FROM shows')->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function search(array $filters, string $sort = 'title', string $dir = '', string $language = 'en'): array
    {
        [$where, $params] = FilterQueryBuilder::build($filters, 's');
        $joins = '';

        if (isset($filters['rating_min'])) {
            $where[] = 'r.rating >= :rating_min';
            $params['rating_min'] = $filters['rating_min'];
        }

        if (isset($filters['list_id'])) {
            $joins .= " JOIN list_items li ON li.item_type = 'show' AND li.item_trakt_id = s.trakt_id";
            $where[] = 'li.list_id = :list_id';
            $params['list_id'] = $filters['list_id'];
        }

        $sortDef = self::SORT_COLUMNS[$sort] ?? self::SORT_COLUMNS['title'];
        $direction = strtoupper($dir) === 'ASC' || strtoupper($dir) === 'DESC' ? strtoupper($dir) : $sortDef['defaultDir'];

        $sql = self::SELECT . $joins
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY {$sortDef['column']} {$direction}";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return array_map(fn ($row) => self::mapRow($row, $language), $stmt->fetchAll());
    }

    public function findOne(int $traktId, string $language = 'en'): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE s.trakt_id = :id');
        $stmt->execute(['id' => $traktId]);
        $row = $stmt->fetch();
        return $row ? self::mapRow($row, $language) : null;
    }

    /** @return string[] */
    public function distinctGenres(): array
    {
        $raw = Database::pdo()->query('SELECT genres FROM shows')->fetchAll(\PDO::FETCH_COLUMN);
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
            'progress' => $row['aired'] !== null ? [
                'aired' => (int) $row['aired'],
                'completed' => (int) $row['completed'],
                'lastWatchedAt' => $row['last_watched_at'],
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
