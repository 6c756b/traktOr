<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;

final class RatingRepository
{
    /**
     * Replaces all ratings of one type with the current Trakt list (also deletes
     * whatever has been removed/de-rated in the meantime).
     *
     * @param 'show'|'movie'|'episode' $itemType
     * @param array<int, array{trakt_id: int, rating: int, rated_at: string}> $rows
     */
    public function replaceAll(string $itemType, array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM ratings WHERE item_type = :item_type')->execute(['item_type' => $itemType]);

        $stmt = $pdo->prepare(
            'INSERT INTO ratings (item_type, trakt_id, rating, rated_at) VALUES (:item_type, :trakt_id, :rating, :rated_at)'
        );
        foreach ($rows as $row) {
            $stmt->execute([
                'item_type' => $itemType,
                'trakt_id' => $row['trakt_id'],
                'rating' => $row['rating'],
                'rated_at' => $row['rated_at'],
            ]);
        }

        $pdo->commit();
    }

    public function upsertOne(string $itemType, int $traktId, int $rating, string $ratedAt): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ratings (item_type, trakt_id, rating, rated_at) VALUES (:item_type, :trakt_id, :rating, :rated_at)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), rated_at = VALUES(rated_at)'
        );
        $stmt->execute(['item_type' => $itemType, 'trakt_id' => $traktId, 'rating' => $rating, 'rated_at' => $ratedAt]);
    }

    public function deleteOne(string $itemType, int $traktId): void
    {
        Database::pdo()->prepare('DELETE FROM ratings WHERE item_type = :item_type AND trakt_id = :trakt_id')
            ->execute(['item_type' => $itemType, 'trakt_id' => $traktId]);
    }
}
