<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;

final class NotesRepository
{
    /** @param 'show'|'movie' $itemType */
    public function upsert(string $itemType, int $traktId, string $note): void
    {
        Database::pdo()->prepare(
            'INSERT INTO notes (item_type, trakt_id, note) VALUES (:item_type, :trakt_id, :note)
             ON DUPLICATE KEY UPDATE note = VALUES(note)'
        )->execute(['item_type' => $itemType, 'trakt_id' => $traktId, 'note' => $note]);
    }

    /** @param 'show'|'movie' $itemType */
    public function delete(string $itemType, int $traktId): void
    {
        Database::pdo()->prepare('DELETE FROM notes WHERE item_type = :item_type AND trakt_id = :trakt_id')
            ->execute(['item_type' => $itemType, 'trakt_id' => $traktId]);
    }
}
