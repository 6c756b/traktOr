<?php

namespace TraktOr\Sync;

use RuntimeException;
use Throwable;
use TraktOr\Db\Repositories\ListRepository;
use TraktOr\Db\Repositories\MovieRepository;
use TraktOr\Db\Repositories\ProgressRepository;
use TraktOr\Db\Repositories\RatingRepository;
use TraktOr\Db\Repositories\SettingsRepository;
use TraktOr\Db\Repositories\ShowRepository;
use TraktOr\Db\Repositories\SyncStateRepository;
use TraktOr\Support\Languages;
use TraktOr\Tmdb\TmdbClient;
use TraktOr\Trakt\TraktClient;

final class SyncService
{
    private TraktClient $trakt;
    private TmdbClient $tmdb;
    private ShowRepository $shows;
    private MovieRepository $movies;
    private ProgressRepository $progress;
    private RatingRepository $ratings;
    private ListRepository $lists;
    private SyncStateRepository $state;
    private string $language;

    public function __construct()
    {
        $this->trakt = new TraktClient();
        $this->tmdb = new TmdbClient();
        $this->shows = new ShowRepository();
        $this->movies = new MovieRepository();
        $this->progress = new ProgressRepository();
        $this->ratings = new RatingRepository();
        $this->lists = new ListRepository();
        $this->state = new SyncStateRepository();
        $this->language = (new SettingsRepository())->getLanguage();
    }

    public function fullSync(): array
    {
        if (!$this->state->tryStartRunning('full')) {
            throw new RuntimeException('Sync läuft bereits.');
        }

        try {
            $shows = $this->syncWatchedShows();
            $result = [
                'shows' => $shows['count'],
                'showsSkipped' => $shows['skipped'],
                'movies' => $this->syncWatchedMovies(),
                'ratings' => $this->syncRatings(),
                'lists' => $this->syncLists(),
            ];
            $warning = $shows['skipped'] > 0
                ? "{$shows['skipped']} Serie(n) beim Sync uebersprungen (Metadaten-Abruf fehlgeschlagen)."
                : null;
            $this->state->markIdle('full', $warning);
            return $result;
        } catch (Throwable $e) {
            $this->state->markError('full', $e->getMessage());
            throw $e;
        }
    }

    /** Marks an episode as watched on Trakt -- together with any earlier episode in the same
     *  season that isn't marked watched yet, matching how people actually watch shows
     *  (ticking off episode 5 after a binge implies 1-4 were seen too). Then resyncs the show. */
    public function markEpisodeWatched(int $showTraktId, int $season, int $number): void
    {
        $this->trakt->post('/sync/history', [
            'shows' => [[
                'ids' => ['trakt' => $showTraktId],
                'seasons' => [[
                    'number' => $season,
                    'episodes' => $this->unwatchedEpisodesUpTo($showTraktId, $season, $number),
                ]],
            ]],
        ]);

        $this->syncShow($showTraktId);
    }

    /** @return array<int, array{number: int}> */
    private function unwatchedEpisodesUpTo(int $showTraktId, int $season, int $number): array
    {
        try {
            $data = $this->trakt->get("/shows/{$showTraktId}/progress/watched?extended=full");
        } catch (Throwable) {
            return [['number' => $number]];
        }

        foreach ($data['seasons'] ?? [] as $s) {
            if ($s['number'] !== $season) {
                continue;
            }
            $episodes = array_values(array_filter(
                $s['episodes'] ?? [],
                fn ($e) => $e['number'] <= $number && !$e['completed']
            ));
            return $episodes !== []
                ? array_map(fn ($e) => ['number' => $e['number']], $episodes)
                : [['number' => $number]];
        }

        return [['number' => $number]];
    }

    /** Marks an episode as unwatched again and then resyncs only this show. */
    public function unmarkEpisodeWatched(int $showTraktId, int $season, int $number): void
    {
        $this->trakt->post('/sync/history/remove', [
            'shows' => [[
                'ids' => ['trakt' => $showTraktId],
                'seasons' => [[
                    'number' => $season,
                    'episodes' => [['number' => $number]],
                ]],
            ]],
        ]);

        $this->syncShow($showTraktId);
    }

    /** Marks an entire season as watched (without "episodes", Trakt marks the whole season). */
    public function markSeasonWatched(int $showTraktId, int $season): void
    {
        $this->trakt->post('/sync/history', [
            'shows' => [[
                'ids' => ['trakt' => $showTraktId],
                'seasons' => [['number' => $season]],
            ]],
        ]);

        $this->syncShow($showTraktId);
    }

    /** Raw season/episode data for the episode list -- progress/metadata live from Trakt,
     *  episode titles from the cache (see resolveEpisodeTitles()). */
    public function getEpisodes(int $showTraktId): array
    {
        $show = $this->trakt->get("/shows/{$showTraktId}?extended=full");
        $tmdbId = $show['ids']['tmdb'] ?? null;

        $data = $this->trakt->get("/shows/{$showTraktId}/progress/watched?extended=full");
        $seasons = $data['seasons'] ?? [];

        $titles = $this->resolveEpisodeTitles($showTraktId, $tmdbId, $seasons);
        $years = $this->resolveSeasonYears($showTraktId);

        return array_map(fn ($season) => [
            'number' => $season['number'],
            'year' => $years[$season['number']] ?? null,
            'aired' => $season['aired'],
            'completed' => $season['completed'],
            'episodes' => array_map(fn ($episode) => [
                'number' => $episode['number'],
                'title' => $titles[$season['number']][$episode['number']] ?? null,
                'completed' => $episode['completed'],
                'lastWatchedAt' => self::toDatetime($episode['last_watched_at'] ?? null),
            ], $season['episodes'] ?? []),
        ], $seasons);
    }

    /** Premiere year per season, straight from Trakt (one call covers every season of the
     *  show) -- 'first_aired' is missing for not-yet-aired seasons. */
    private function resolveSeasonYears(int $showTraktId): array
    {
        $seasons = $this->trakt->get("/shows/{$showTraktId}/seasons?extended=full");

        $years = [];
        foreach ($seasons as $season) {
            $firstAired = $season['first_aired'] ?? null;
            $years[$season['number']] = $firstAired ? (int) substr($firstAired, 0, 4) : null;
        }
        return $years;
    }

    /**
     * Episode titles for the current language -- first from shows.episode_translations, only
     * seasons with missing episodes (newly aired episodes or first-time lookup) are fetched
     * via TMDB (or Trakt fallback for English/no TMDB match) and written back.
     * Titles that have already been assigned practically never change after their initial
     * release -- a manual "sync now" covers the rare correction case.
     *
     * @return array<int, array<int, string>> [season => [episode number => title]]
     */
    private function resolveEpisodeTitles(int $showTraktId, ?int $tmdbId, array $seasons): array
    {
        $cached = $this->shows->getEpisodeTranslations($showTraktId, $this->language);

        $missingSeasons = [];
        foreach ($seasons as $season) {
            $knownEpisodes = array_map(fn ($e) => $e['number'], $season['episodes'] ?? []);
            $cachedEpisodes = array_keys($cached[$season['number']] ?? []);
            if ($knownEpisodes !== [] && array_diff($knownEpisodes, $cachedEpisodes) !== []) {
                $missingSeasons[] = $season['number'];
            }
        }

        if ($missingSeasons === []) {
            return $cached;
        }

        $fetched = ($tmdbId && $this->language !== 'en')
            ? $this->tmdb->getManySeasonEpisodeTitles($tmdbId, $missingSeasons, Languages::locale($this->language))
            : [];

        if ($fetched === []) {
            $seasonPaths = array_map(fn ($n) => "/shows/{$showTraktId}/seasons/{$n}", $missingSeasons);
            $seasonResults = $this->trakt->getMany($seasonPaths);
            foreach ($seasonResults as $episodes) {
                foreach ($episodes as $episode) {
                    $title = $episode['title'] ?? null;
                    // Don't store null -- JSON_MERGE_PATCH would interpret a null value as
                    // "delete key", and a missing key already means
                    // "no title known yet, try again next time" anyway.
                    if ($title !== null) {
                        $fetched[$episode['season']][$episode['number']] = $title;
                    }
                }
            }
        }

        if ($fetched !== []) {
            $this->shows->mergeEpisodeTranslations($showTraktId, $this->language, $fetched);
        }

        // $fetched first: for seasons that were just freshly loaded, it's the
        // more complete/fresher source than the (possibly incomplete) cache state.
        return $fetched + $cached;
    }

    /** @param 'show'|'movie' $itemType */
    public function rateItem(string $itemType, int $traktId, int $rating): void
    {
        $key = $itemType === 'movie' ? 'movies' : 'shows';
        $this->trakt->post('/sync/ratings', [$key => [['ids' => ['trakt' => $traktId], 'rating' => $rating]]]);
        $this->ratings->upsertOne($itemType, $traktId, $rating, date('Y-m-d H:i:s'));
    }

    /** @param 'show'|'movie' $itemType */
    public function unrateItem(string $itemType, int $traktId): void
    {
        $key = $itemType === 'movie' ? 'movies' : 'shows';
        $this->trakt->post('/sync/ratings/remove', [$key => [['ids' => ['trakt' => $traktId]]]]);
        $this->ratings->deleteOne($itemType, $traktId);
    }

    /** Resyncs metadata + progress for a single show, e.g. after a watch mutation. */
    public function syncShow(int $traktId): void
    {
        $show = $this->trakt->get("/shows/{$traktId}?extended=full");
        $tmdbId = $show['ids']['tmdb'] ?? null;
        $details = $tmdbId ? $this->tmdb->getManyDetails([$tmdbId], 'tv', Languages::locale($this->language)) : [];

        $this->shows->upsert($this->mapShow($show));
        $this->applyShowTranslation($show['ids']['trakt'], $tmdbId, $details);

        $progressData = $this->trakt->get("/shows/{$traktId}/progress/watched?extended=full");
        $this->progress->upsert($this->mapProgress($traktId, $progressData));
    }

    /** @return array{count: int, skipped: int} */
    private function syncWatchedShows(): array
    {
        // /sync/watched/shows returns the embedded show object without "overview" even
        // with extended=full -- for full metadata, /shows/:id?extended=full must be
        // fetched individually (batched) for each show.
        $watched = $this->trakt->get('/sync/watched/shows');
        if ($watched === []) {
            return ['count' => 0, 'skipped' => 0];
        }

        $traktIds = array_map(fn ($entry) => $entry['show']['ids']['trakt'], $watched);

        $metaPaths = array_map(fn ($id) => "/shows/{$id}?extended=full", $traktIds);
        $metaResults = $this->trakt->getMany($metaPaths);
        $showsById = [];
        foreach ($metaResults as $show) {
            $showsById[$show['ids']['trakt']] = $show;
        }

        $tmdbIds = array_values(array_filter(array_map(
            fn ($show) => $show['ids']['tmdb'] ?? null,
            $showsById
        )));
        $details = $this->tmdb->getManyDetails($tmdbIds, 'tv', Languages::locale($this->language));

        $progressPathToShowId = [];
        foreach ($traktIds as $traktId) {
            // If the metadata call for a show failed: skip it entirely.
            // show_progress has an FK on shows -- without a show row, the
            // progress insert would blow up the entire sync with an exception.
            if (!isset($showsById[$traktId])) {
                continue;
            }
            $show = $showsById[$traktId];
            $this->shows->upsert($this->mapShow($show));
            $this->applyShowTranslation($traktId, $show['ids']['tmdb'] ?? null, $details);
            $progressPathToShowId["/shows/{$traktId}/progress/watched?extended=full"] = $traktId;
        }

        $progressResults = $this->trakt->getMany(array_keys($progressPathToShowId));
        foreach ($progressResults as $path => $data) {
            $this->progress->upsert($this->mapProgress($progressPathToShowId[$path], $data));
        }

        return [
            'count' => count($showsById),
            'skipped' => count($traktIds) - count($showsById),
        ];
    }

    private function syncWatchedMovies(): int
    {
        $watched = $this->trakt->get('/sync/watched/movies?extended=full');
        if ($watched === []) {
            return 0;
        }

        $tmdbIds = array_values(array_filter(array_map(
            fn ($entry) => $entry['movie']['ids']['tmdb'] ?? null,
            $watched
        )));
        $details = $this->tmdb->getManyDetails($tmdbIds, 'movie', Languages::locale($this->language));

        foreach ($watched as $entry) {
            $movie = $entry['movie'];
            $this->movies->upsert($this->mapMovie($movie));
            $this->applyMovieTranslation($movie['ids']['trakt'], $movie['ids']['tmdb'] ?? null, $details);
        }

        return count($watched);
    }

    private function syncRatings(): int
    {
        $count = 0;
        foreach (['shows' => 'show', 'movies' => 'movie'] as $endpoint => $itemType) {
            $entries = $this->trakt->get("/sync/ratings/{$endpoint}");
            $rows = array_map(fn ($entry) => [
                'trakt_id' => $entry[$itemType]['ids']['trakt'],
                'rating' => $entry['rating'],
                'rated_at' => self::toDatetime($entry['rated_at']),
            ], $entries);
            $this->ratings->replaceAll($itemType, $rows);
            $count += count($rows);
        }
        return $count;
    }

    private function syncLists(): int
    {
        $traktLists = $this->trakt->get('/users/me/lists');
        if ($traktLists === []) {
            $this->lists->replaceAll([], []);
            return 0;
        }

        $itemPaths = [];
        foreach ($traktLists as $list) {
            $listId = $list['ids']['trakt'];
            $itemPaths["/users/me/lists/{$listId}/items"] = $listId;
        }
        $itemResults = $this->trakt->getMany(array_keys($itemPaths));

        $lists = array_map(fn ($list) => [
            'trakt_list_id' => $list['ids']['trakt'],
            'name' => $list['name'],
            'slug' => $list['ids']['slug'],
        ], $traktLists);

        $itemsByListId = [];
        foreach ($itemResults as $path => $items) {
            $listId = $itemPaths[$path];
            $itemsByListId[$listId] = array_values(array_filter(array_map(function ($item) {
                if (!in_array($item['type'], ['show', 'movie'], true)) {
                    return null;
                }
                return [
                    'item_type' => $item['type'],
                    'item_trakt_id' => $item[$item['type']]['ids']['trakt'],
                ];
            }, $items)));
        }

        $this->lists->replaceAll($lists, $itemsByListId);
        return count($lists);
    }

    /** title/overview are always Trakt's original (English) -- guaranteed fallback, see applyShowTranslation(). */
    private function mapShow(array $show): array
    {
        return [
            'trakt_id' => $show['ids']['trakt'],
            'slug' => $show['ids']['slug'],
            'title' => $show['title'],
            'year' => $show['year'] ?? null,
            'overview' => $show['overview'] ?? null,
            'status' => $show['status'] ?? null,
            'network' => $show['network'] ?? null,
            'runtime' => $show['runtime'] ?? null,
            'first_aired' => self::toDatetime($show['first_aired'] ?? null),
            'genres' => json_encode($show['genres'] ?? []),
            // Poster/backdrop are set separately via applyShowTranslation()/updateImages().
            'poster_url' => null,
            'backdrop_url' => null,
            'aired_episodes' => $show['aired_episodes'] ?? null,
            'certification' => $show['certification'] ?? null,
            'raw_json' => json_encode($show),
        ];
    }

    private function mapMovie(array $movie): array
    {
        return [
            'trakt_id' => $movie['ids']['trakt'],
            'slug' => $movie['ids']['slug'],
            'title' => $movie['title'],
            'year' => $movie['year'] ?? null,
            'overview' => $movie['overview'] ?? null,
            'status' => $movie['status'] ?? null,
            'genres' => json_encode($movie['genres'] ?? []),
            'poster_url' => null,
            'backdrop_url' => null,
            'runtime' => $movie['runtime'] ?? null,
            'released' => $movie['released'] ?? null,
            'certification' => $movie['certification'] ?? null,
            'raw_json' => json_encode($movie),
        ];
    }

    /** Sets poster/backdrop (always) and merges the translation for the current language (if != 'en'). */
    private function applyShowTranslation(int $traktId, ?int $tmdbId, array $details): void
    {
        $detail = $tmdbId ? ($details[$tmdbId] ?? null) : null;
        if ($detail === null) {
            return;
        }

        $this->shows->updateImages($traktId, $detail['poster_url'], $detail['backdrop_url']);

        if ($this->language !== 'en') {
            $this->shows->mergeTranslation(
                $traktId,
                $this->language,
                $detail['title'] !== '' ? $detail['title'] : null,
                $detail['overview'] !== '' ? $detail['overview'] : null
            );
        }
    }

    private function applyMovieTranslation(int $traktId, ?int $tmdbId, array $details): void
    {
        $detail = $tmdbId ? ($details[$tmdbId] ?? null) : null;
        if ($detail === null) {
            return;
        }

        $this->movies->updateImages($traktId, $detail['poster_url'], $detail['backdrop_url']);

        if ($this->language !== 'en') {
            $this->movies->mergeTranslation(
                $traktId,
                $this->language,
                $detail['title'] !== '' ? $detail['title'] : null,
                $detail['overview'] !== '' ? $detail['overview'] : null
            );
        }
    }

    private function mapProgress(int $traktShowId, array $data): array
    {
        $next = $data['next_episode'] ?? null;
        $last = $data['last_episode'] ?? null;
        return [
            'trakt_show_id' => $traktShowId,
            'aired' => $data['aired'] ?? 0,
            'completed' => $data['completed'] ?? 0,
            'last_watched_at' => self::toDatetime($data['last_watched_at'] ?? null),
            'last_episode_season' => $last['season'] ?? null,
            'last_episode_number' => $last['number'] ?? null,
            'next_episode_season' => $next['season'] ?? null,
            'next_episode_number' => $next['number'] ?? null,
            'next_episode_title' => $next['title'] ?? null,
            'next_episode_first_aired' => self::toDatetime($next['first_aired'] ?? null),
            'reset_at' => self::toDatetime($data['reset_at'] ?? null),
        ];
    }

    private static function toDatetime(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }
        $timestamp = strtotime($iso);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
