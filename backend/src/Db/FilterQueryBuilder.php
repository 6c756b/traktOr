<?php

namespace TraktOr\Db;

/** Builds the shared WHERE clause for show/movie library filters (genre, status, year, search). */
final class FilterQueryBuilder
{
    /** @return array{0: string[], 1: array<string, mixed>} [$whereClauses, $params] */
    public static function build(array $filters, string $alias): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['genres'])) {
            $clauses = [];
            foreach (array_values($filters['genres']) as $i => $genre) {
                $key = "genre{$i}";
                $clauses[] = "JSON_CONTAINS({$alias}.genres, JSON_QUOTE(:{$key}))";
                $params[$key] = $genre;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }

        if (!empty($filters['statuses'])) {
            $placeholders = [];
            foreach (array_values($filters['statuses']) as $i => $status) {
                $key = "status{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $status;
            }
            $where[] = "{$alias}.status IN (" . implode(',', $placeholders) . ')';
        }

        if (isset($filters['year_min'])) {
            $where[] = "{$alias}.year >= :year_min";
            $params['year_min'] = $filters['year_min'];
        }
        if (isset($filters['year_max'])) {
            $where[] = "{$alias}.year <= :year_max";
            $params['year_max'] = $filters['year_max'];
        }

        if (!empty($filters['search'])) {
            $where[] = "{$alias}.title LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        return [$where, $params];
    }

    /** @param string[] $rawGenreArrays JSON-encoded genres columns (one per row) */
    public static function distinctGenres(array $rawGenreArrays): array
    {
        $genres = [];
        foreach ($rawGenreArrays as $raw) {
            foreach (json_decode($raw ?? '[]', true) ?? [] as $genre) {
                $genres[$genre] = true;
            }
        }
        $result = array_keys($genres);
        sort($result);
        return $result;
    }
}
