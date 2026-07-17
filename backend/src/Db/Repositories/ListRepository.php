<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;

final class ListRepository
{
    /**
     * @param array<int, array{trakt_list_id: int, name: string, slug: string}> $lists
     * @param array<int, array<int, array{item_type: 'show'|'movie', item_trakt_id: int}>> $itemsByListId
     */
    public function replaceAll(array $lists, array $itemsByListId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        // list_items hangs off lists via FK ON DELETE CASCADE -- one delete is enough.
        $pdo->exec('DELETE FROM lists');

        $listStmt = $pdo->prepare('INSERT INTO lists (trakt_list_id, name, slug) VALUES (:trakt_list_id, :name, :slug)');
        $itemStmt = $pdo->prepare(
            'INSERT INTO list_items (list_id, item_type, item_trakt_id) VALUES (:list_id, :item_type, :item_trakt_id)'
        );

        foreach ($lists as $list) {
            $listStmt->execute($list);
            foreach ($itemsByListId[$list['trakt_list_id']] ?? [] as $item) {
                $itemStmt->execute([
                    'list_id' => $list['trakt_list_id'],
                    'item_type' => $item['item_type'],
                    'item_trakt_id' => $item['item_trakt_id'],
                ]);
            }
        }

        $pdo->commit();
    }

    public function all(): array
    {
        $rows = Database::pdo()->query('SELECT trakt_list_id, name, slug FROM lists ORDER BY name')->fetchAll();
        return array_map(fn ($row) => [
            'id' => (int) $row['trakt_list_id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
        ], $rows);
    }
}
