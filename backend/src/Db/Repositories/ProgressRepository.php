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

    /** @param int[] $traktShowIds @return int[] the subset with any watched progress */
    public function watchedShowIds(array $traktShowIds): array
    {
        if ($traktShowIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($traktShowIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT trakt_show_id FROM show_progress WHERE completed > 0 AND trakt_show_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($traktShowIds));
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function setHidden(int $traktShowId, bool $hidden): void
    {
        Database::pdo()->prepare('UPDATE show_progress SET hidden = :hidden WHERE trakt_show_id = :id')
            ->execute(['hidden' => $hidden ? 1 : 0, 'id' => $traktShowId]);
    }

    /** Full reconciliation with Trakt's hidden-progress list (source of truth) -- unhides
     *  everything first, then re-hides exactly the shows Trakt currently reports, so a show
     *  unhidden directly on trakt.tv also un-hides here on the next sync.
     *
     * @param int[] $hiddenTraktIds
     */
    public function replaceHiddenFlags(array $hiddenTraktIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        $pdo->exec('UPDATE show_progress SET hidden = 0');

        if ($hiddenTraktIds !== []) {
            $placeholders = implode(',', array_fill(0, count($hiddenTraktIds), '?'));
            $pdo->prepare("UPDATE show_progress SET hidden = 1 WHERE trakt_show_id IN ({$placeholders})")
                ->execute(array_values($hiddenTraktIds));
        }

        $pdo->commit();
    }
}
