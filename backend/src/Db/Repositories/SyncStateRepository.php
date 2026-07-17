<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;

final class SyncStateRepository
{
    private const STALE_LOCK_MINUTES = 10;

    /** Attempts to acquire the lock. false = already running (and the lock is still fresh). */
    public function tryStartRunning(string $resource): bool
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO sync_state (resource, status) VALUES (:resource, "idle")
             ON DUPLICATE KEY UPDATE resource = resource'
        )->execute(['resource' => $resource]);

        $stmt = $pdo->prepare(
            'UPDATE sync_state SET status = "running", updated_at = NOW()
             WHERE resource = :resource
               AND (status != "running" OR updated_at < NOW() - INTERVAL :stale_minutes MINUTE)'
        );
        $stmt->execute(['resource' => $resource, 'stale_minutes' => self::STALE_LOCK_MINUTES]);

        return $stmt->rowCount() > 0;
    }

    public function markIdle(string $resource, ?string $warning = null): void
    {
        Database::pdo()->prepare(
            'UPDATE sync_state SET status = "idle", last_synced_at = NOW(), last_error = NULL, last_warning = :warning
             WHERE resource = :resource'
        )->execute(['resource' => $resource, 'warning' => $warning]);
    }

    public function markError(string $resource, string $message): void
    {
        Database::pdo()->prepare(
            'UPDATE sync_state SET status = "error", last_error = :message WHERE resource = :resource'
        )->execute(['resource' => $resource, 'message' => $message]);
    }

    public function isStale(string $resource, int $maxAgeMinutes): bool
    {
        $stmt = Database::pdo()->prepare('SELECT last_synced_at FROM sync_state WHERE resource = :resource');
        $stmt->execute(['resource' => $resource]);
        $lastSyncedAt = $stmt->fetchColumn();

        if (!$lastSyncedAt) {
            return true;
        }

        return strtotime($lastSyncedAt) < time() - ($maxAgeMinutes * 60);
    }

    public function all(): array
    {
        return Database::pdo()->query('SELECT * FROM sync_state')->fetchAll();
    }
}
