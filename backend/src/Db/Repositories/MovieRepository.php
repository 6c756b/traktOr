<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;
use TraktOr\Db\FilterQueryBuilder;

final class MovieRepository
{
    private const SORT_COLUMNS = [
        'title' => ['column' => 'm.title', 'defaultDir' => 'ASC'],
        'year' => ['column' => 'm.year', 'defaultDir' => 'DESC'],
        'rating' => ['column' => 'r.rating', 'defaultDir' => 'DESC'],
    ];

    private const SELECT = "SELECT
            m.trakt_id, m.slug, m.title, m.year, m.overview, m.status, m.genres,
            m.poster_url, m.backdrop_url, m.runtime, m.released, m.certification, m.translations,
            r.rating
        FROM movies m
        LEFT JOIN ratings r ON r.item_type = 'movie' AND r.trakt_id = m.trakt_id";

    public function upsert(array $row): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO movies (
                trakt_id, slug, title, year, overview, status, genres,
                poster_url, backdrop_url, runtime, released, certification, raw_json
            ) VALUES (
                :trakt_id, :slug, :title, :year, :overview, :status, :genres,
                :poster_url, :backdrop_url, :runtime, :released, :certification, :raw_json
            )
            ON DUPLICATE KEY UPDATE
                slug = VALUES(slug), title = VALUES(title), year = VALUES(year),
                overview = VALUES(overview), status = VALUES(status), genres = VALUES(genres),
                poster_url = COALESCE(VALUES(poster_url), poster_url),
                backdrop_url = COALESCE(VALUES(backdrop_url), backdrop_url),
                runtime = VALUES(runtime), released = VALUES(released),
                certification = VALUES(certification), raw_json = VALUES(raw_json)'
        );
        $stmt->execute($row);
    }

    public function updateImages(int $traktId, ?string $posterUrl, ?string $backdropUrl): void
    {
        Database::pdo()->prepare(
            'UPDATE movies SET
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
            'UPDATE movies SET translations = JSON_MERGE_PATCH(COALESCE(translations, "{}"), :fragment) WHERE trakt_id = :id'
        )->execute(['fragment' => $fragment, 'id' => $traktId]);
    }

    public function search(array $filters, string $sort = 'title', string $dir = '', string $language = 'en'): array
    {
        [$where, $params] = FilterQueryBuilder::build($filters, 'm');
        $joins = '';

        if (isset($filters['rating_min'])) {
            $where[] = 'r.rating >= :rating_min';
            $params['rating_min'] = $filters['rating_min'];
        }

        if (isset($filters['list_id'])) {
            $joins .= " JOIN list_items li ON li.item_type = 'movie' AND li.item_trakt_id = m.trakt_id";
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
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE m.trakt_id = :id');
        $stmt->execute(['id' => $traktId]);
        $row = $stmt->fetch();
        return $row ? self::mapRow($row, $language) : null;
    }

    /** @return string[] */
    public function distinctGenres(): array
    {
        $raw = Database::pdo()->query('SELECT genres FROM movies')->fetchAll(\PDO::FETCH_COLUMN);
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
            'genres' => json_decode($row['genres'] ?? '[]', true) ?? [],
            'posterUrl' => $row['poster_url'],
            'backdropUrl' => $row['backdrop_url'],
            'runtime' => $row['runtime'] !== null ? (int) $row['runtime'] : null,
            'released' => $row['released'],
            'certification' => $row['certification'],
            'rating' => $row['rating'] !== null ? (int) $row['rating'] : null,
        ];
    }
}
