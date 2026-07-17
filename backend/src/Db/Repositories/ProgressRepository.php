<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;

final class ProgressRepository
{
    public function upsert(array $row): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO show_progress (
                trakt_show_id, aired, completed, last_watched_at,
                last_episode_season, last_episode_number,
                next_episode_season, next_episode_number, next_episode_title, next_episode_first_aired,
                reset_at
            ) VALUES (
                :trakt_show_id, :aired, :completed, :last_watched_at,
                :last_episode_season, :last_episode_number,
                :next_episode_season, :next_episode_number, :next_episode_title, :next_episode_first_aired,
                :reset_at
            )
            ON DUPLICATE KEY UPDATE
                aired = VALUES(aired), completed = VALUES(completed), last_watched_at = VALUES(last_watched_at),
                last_episode_season = VALUES(last_episode_season), last_episode_number = VALUES(last_episode_number),
                next_episode_season = VALUES(next_episode_season), next_episode_number = VALUES(next_episode_number),
                next_episode_title = VALUES(next_episode_title), next_episode_first_aired = VALUES(next_episode_first_aired),
                reset_at = VALUES(reset_at)'
        );
        $stmt->execute($row);
    }
}
