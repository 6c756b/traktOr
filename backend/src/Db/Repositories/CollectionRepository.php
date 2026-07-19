<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;

final class CollectionRepository
{
    /**
     * Replaces one item type's collection rows with the current Trakt state.
     * /sync/collection/movies and /sync/collection/shows are SEPARATE Trakt calls
     * (unlike /sync/watchlist's single mixed response), so $itemType is never null here.
     *
     * @param 'show'|'movie' $itemType
     * @param array<int, array{item_type: 'show'|'movie', item_trakt_id: int, season_number: int, collected_at: string}> $rows
     */
    public function replaceAll(string $itemType, array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM collection_items WHERE item_type = :item_type')->execute(['item_type' => $itemType]);

        $stmt = $pdo->prepare(
            'INSERT INTO collection_items (item_type, item_trakt_id, season_number, collected_at)
             VALUES (:item_type, :item_trakt_id, :season_number, :collected_at)'
        );
        foreach ($rows as $row) {
            $stmt->execute($row);
        }

        $pdo->commit();
    }

    /** @param 'show'|'movie' $itemType -- season_number is always 0 for movies. */
    public function upsertOne(string $itemType, int $traktId, int $seasonNumber, string $collectedAtIso): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO collection_items (item_type, item_trakt_id, season_number, collected_at)
             VALUES (:item_type, :item_trakt_id, :season_number, :collected_at)
             ON DUPLICATE KEY UPDATE collected_at = VALUES(collected_at)'
        );
        $stmt->execute([
            'item_type' => $itemType,
            'item_trakt_id' => $traktId,
            'season_number' => $seasonNumber,
            'collected_at' => $collectedAtIso,
        ]);
    }

    public function deleteOne(string $itemType, int $traktId, int $seasonNumber): void
    {
        Database::pdo()->prepare(
            'DELETE FROM collection_items WHERE item_type = :item_type AND item_trakt_id = :trakt_id AND season_number = :season_number'
        )->execute(['item_type' => $itemType, 'trakt_id' => $traktId, 'season_number' => $seasonNumber]);
    }

    /** @param int[] $traktIds @return int[] the subset of movie trakt IDs that are collected */
    public function collectedTraktIds(array $traktIds): array
    {
        if ($traktIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($traktIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT item_trakt_id FROM collection_items
             WHERE item_type = 'movie' AND season_number = 0 AND item_trakt_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($traktIds));
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return int[] collected season numbers for one show */
    public function collectedSeasons(int $showTraktId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT season_number FROM collection_items WHERE item_type = 'show' AND item_trakt_id = :id ORDER BY season_number"
        );
        $stmt->execute(['id' => $showTraktId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
