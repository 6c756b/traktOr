<?php

namespace TraktOr\Sync;

use TraktOr\Db\Database;
use TraktOr\Db\Repositories\SettingsRepository;
use TraktOr\Db\Repositories\SyncStateRepository;

final class ContinueWatchingBuilder
{
    // The primary sync mechanism is now the nightly cronjob (see
    // TraktOr\Cron\NightlySyncJob); 26h buffer over its 24h cadence -- this only kicks in
    // as a fallback here if the cron has failed to run for a longer stretch.
    private const STALE_MINUTES = 26 * 60;
    private const BASE_QUERY = "SELECT
            s.trakt_id, s.slug, s.title, s.translations, s.poster_url, s.backdrop_url, s.genres,
            sp.aired, sp.completed, sp.last_watched_at,
            sp.next_episode_season, sp.next_episode_number,
            sp.next_episode_title, sp.next_episode_first_aired
        FROM show_progress sp
        JOIN shows s ON s.trakt_id = sp.trakt_show_id
        WHERE sp.completed < sp.aired
          AND sp.next_episode_first_aired IS NOT NULL
          AND sp.next_episode_first_aired <= NOW()
          AND sp.hidden = 0";

    public function build(string $sort = 'new'): array
    {
        $language = (new SettingsRepository())->getLanguage();

        $orderBy = $sort === 'waiting'
            ? 'sp.last_watched_at ASC'
            : 'sp.next_episode_first_aired DESC';

        $stmt = Database::pdo()->query(self::BASE_QUERY . " ORDER BY {$orderBy}");

        return array_map(fn ($row) => self::mapRow($row, $language), $stmt->fetchAll());
    }

    /** A single show, e.g. to return the current card state after a watch mutation. */
    public function buildOne(int $showTraktId): ?array
    {
        $language = (new SettingsRepository())->getLanguage();
        $stmt = Database::pdo()->prepare(self::BASE_QUERY . ' AND s.trakt_id = :id');
        $stmt->execute(['id' => $showTraktId]);
        $row = $stmt->fetch();
        return $row ? self::mapRow($row, $language) : null;
    }

    private static function mapRow(array $row, string $language): array
    {
        $translations = json_decode($row['translations'] ?? '{}', true) ?? [];
        $title = $translations[$language]['title'] ?? $row['title'];

        return [
            'id' => (int) $row['trakt_id'],
            'slug' => $row['slug'],
            'title' => $title,
            'posterUrl' => $row['poster_url'],
            'backdropUrl' => $row['backdrop_url'],
            'genres' => json_decode($row['genres'] ?? '[]', true) ?? [],
            'newEpisodesCount' => (int) $row['aired'] - (int) $row['completed'],
            'lastWatchedAt' => $row['last_watched_at'],
            'nextEpisode' => [
                'season' => (int) $row['next_episode_season'],
                'number' => (int) $row['next_episode_number'],
                'title' => $row['next_episode_title'],
                'firstAired' => $row['next_episode_first_aired'],
            ],
        ];
    }

    /** Whether the last full sync is older than STALE_MINUTES -- the frontend then triggers
     *  POST /sync/full itself in the background, instead of this request waiting for it. */
    public function isStale(): bool
    {
        return (new SyncStateRepository())->isStale('full', self::STALE_MINUTES);
    }
}
