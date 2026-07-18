<?php

namespace TraktOr\Cron;

use DateTime;
use DateTimeZone;
use TraktOr\Db\Repositories\SyncStateRepository;
use TraktOr\Sync\SyncService;

/**
 * Syncs once per night instead of on every page load. The combination of the hour window
 * (only active at night) and the 20h staleness check (already in place for the frontend
 * fallback) reliably adds up to "once per night", even if the host's cron fires multiple
 * times within the window -- after the first successful run, isStale(20h) stays false for
 * the rest of the window and the following day.
 */
final class NightlySyncJob
{
    private const START_HOUR = 3;
    private const END_HOUR = 5;
    private const STALE_HOURS = 20;

    private DateTimeZone $timezone;

    public function __construct()
    {
        $configFile = __DIR__ . '/../../config/config.php';
        $config = is_file($configFile) ? require $configFile : [];
        // Deliberately a SEPARATE, explicit timezone only for the "is it night right now?"
        // question -- the global PHP timezone stays UTC (see bootstrap.php), so isStale()
        // keeps comparing correctly against the (also UTC-normalized) DB timestamps.
        $this->timezone = new DateTimeZone($config['cron']['timezone'] ?? 'UTC');
    }

    /** $currentHour only for testing -- during actual cron runs, always the real local hour. */
    public function runIfDue(?int $currentHour = null): void
    {
        $hour = $currentHour ?? (int) (new DateTime('now', $this->timezone))->format('G');
        if ($hour < self::START_HOUR || $hour >= self::END_HOUR) {
            echo "NightlySyncJob: außerhalb des Nachtfensters ({$hour} Uhr {$this->timezone->getName()}), übersprungen.\n";
            return;
        }

        if (!(new SyncStateRepository())->isStale('full', self::STALE_HOURS * 60)) {
            echo "NightlySyncJob: bereits aktuell, nichts zu tun.\n";
            return;
        }

        echo "NightlySyncJob: starte Full-Sync...\n";
        $result = (new SyncService())->fullSync();
        echo "NightlySyncJob: fertig - {$result['shows']} Serien, {$result['movies']} Filme, "
            . "{$result['ratings']} Bewertungen, {$result['lists']} Listen, {$result['watchlist']} Watchlist-Einträge.\n";
    }
}
