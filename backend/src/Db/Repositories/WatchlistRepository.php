<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;

final class WatchlistRepository
{
    /**
     * Replaces the watchlist (or one item type of it) with the current Trakt state.
     * $itemType = null replaces the whole table in one pass -- /sync/watchlist returns
     * shows and movies mixed in a single response, so the primary sync path never needs
     * the type-scoped variant (kept for parity with RatingRepository/deleteOne symmetry).
     *
     * @param 'show'|'movie'|null $itemType
     * @param array<int, array{item_type: 'show'|'movie', item_trakt_id: int, listed_at: string}> $rows
     */
    public function replaceAll(?string $itemType, array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        if ($itemType === null) {
            $pdo->exec('DELETE FROM watchlist_items');
        } else {
            $pdo->prepare('DELETE FROM watchlist_items WHERE item_type = :item_type')->execute(['item_type' => $itemType]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO watchlist_items (item_type, item_trakt_id, listed_at) VALUES (:item_type, :item_trakt_id, :listed_at)'
        );
        foreach ($rows as $row) {
            $stmt->execute($row);
        }

        $pdo->commit();
    }

    public function upsertOne(string $itemType, int $traktId, string $listedAt): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO watchlist_items (item_type, item_trakt_id, listed_at) VALUES (:item_type, :item_trakt_id, :listed_at)
             ON DUPLICATE KEY UPDATE listed_at = VALUES(listed_at)'
        );
        $stmt->execute(['item_type' => $itemType, 'item_trakt_id' => $traktId, 'listed_at' => $listedAt]);
    }

    /** @param 'show'|'movie' $itemType @param int[] $traktIds @return int[] the subset already on the watchlist */
    public function watchlistedTraktIds(string $itemType, array $traktIds): array
    {
        if ($traktIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($traktIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT item_trakt_id FROM watchlist_items WHERE item_type = ? AND item_trakt_id IN ({$placeholders})"
        );
        $stmt->execute([$itemType, ...$traktIds]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function deleteOne(string $itemType, int $traktId): void
    {
        Database::pdo()->prepare('DELETE FROM watchlist_items WHERE item_type = :item_type AND item_trakt_id = :trakt_id')
            ->execute(['item_type' => $itemType, 'trakt_id' => $traktId]);
    }
}
