<?php

namespace TraktOr\Sync;

use RuntimeException;
use Throwable;
use TraktOr\Db\Repositories\CollectionRepository;
use TraktOr\Db\Repositories\ListRepository;
use TraktOr\Db\Repositories\MovieRepository;
use TraktOr\Db\Repositories\ProgressRepository;
use TraktOr\Db\Repositories\RatingRepository;
use TraktOr\Db\Repositories\SettingsRepository;
use TraktOr\Db\Repositories\ShowRepository;
use TraktOr\Db\Repositories\SyncStateRepository;
use TraktOr\Db\Repositories\WatchlistRepository;
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
    private WatchlistRepository $watchlist;
    private CollectionRepository $collection;
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
        $this->watchlist = new WatchlistRepository();
        $this->collection = new CollectionRepository();
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
                'watchlist' => $this->syncWatchlist(),
                'collection' => $this->syncCollection(),
                'hiddenShows' => $this->syncHiddenShows(),
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

    /** Marks an arbitrary set of episodes (any seasons) as watched in one Trakt call, then
     *  resyncs -- used for earlier-unwatched episodes the user confirmed marking alongside
     *  the episode/season they actually clicked (markEpisodeWatched()'s own cascade only
     *  covers gaps within the same season).
     * @param array<int, array{season:int, number:int}> $episodes */
    public function markEpisodesWatched(int $showTraktId, array $episodes): void
    {
        if ($episodes === []) {
            return;
        }

        $bySeason = [];
        foreach ($episodes as $episode) {
            $bySeason[$episode['season']][] = ['number' => $episode['number']];
        }

        $this->trakt->post('/sync/history', [
            'shows' => [[
                'ids' => ['trakt' => $showTraktId],
                'seasons' => array_map(
                    fn ($season, $eps) => ['number' => $season, 'episodes' => $eps],
                    array_keys($bySeason),
                    array_values($bySeason)
                ),
            ]],
        ]);

        $this->syncShow($showTraktId);
    }

    /** Season/episode shape for the episode list -- cacheable, rarely changes. Episode titles
     *  come from the existing per-language cache (see resolveEpisodeTitles()); watched status
     *  is a separate, always-live call (see getProgress()). */
    public function getSeasonShape(int $showTraktId): array
    {
        $tmdbId = $this->shows->getTmdbId($showTraktId);
        $structure = $this->shows->getSeasonStructure($showTraktId);
        if ($structure === null) {
            $structure = $this->buildSeasonStructure($showTraktId, null);
        }

        $titles = $this->resolveEpisodeTitles($showTraktId, $tmdbId, $structure['seasons']);

        return array_map(fn ($season) => [
            'number' => $season['number'],
            'year' => $season['year'],
            'episodes' => array_map(fn ($number) => [
                'number' => $number,
                'title' => $titles[$season['number']][$number] ?? null,
            ], $season['episodeNumbers']),
        ], $structure['seasons']);
    }

    /** Live per-episode watched status -- always fresh from Trakt, never cached (completion
     *  counts change on every watch mutation, unlike the shape above). As a side effect,
     *  opportunistically rebuilds the season_structure cache if Trakt's aired-episode count
     *  has moved since it was last built -- this runs on every page view (not just after a
     *  watch mutation through this app), so even a show that's only ever watched elsewhere
     *  (Trakt's own app/TV client) keeps its structure cache from going stale indefinitely. */
    public function getProgress(int $showTraktId): array
    {
        $data = $this->trakt->get("/shows/{$showTraktId}/progress/watched?extended=full");

        $cached = $this->shows->getSeasonStructure($showTraktId);
        $liveAired = $data['aired'] ?? null;
        if ($cached === null || ($cached['airedEpisodes'] ?? null) !== $liveAired) {
            $this->buildSeasonStructure($showTraktId, $liveAired);
        }

        return array_map(fn ($season) => [
            'number' => $season['number'],
            'aired' => $season['aired'],
            'completed' => $season['completed'],
            'episodes' => array_map(fn ($episode) => [
                'number' => $episode['number'],
                'completed' => $episode['completed'],
                'lastWatchedAt' => self::toDatetime($episode['last_watched_at'] ?? null),
            ], $season['episodes'] ?? []),
        ], $data['seasons'] ?? []);
    }

    /** One Trakt call for the whole show's season/episode shape -- extended=episodes embeds
     *  each season's full episode list, extended=full adds first_aired for the premiere year.
     *  Specials (season 0) are dropped to match the old behavior (previously sourced from
     *  /progress/watched, which excludes them by default). Cached in shows.season_structure
     *  until getProgress() detects it's gone stale.
     *
     * @return array{airedEpisodes: ?int, seasons: array<int, array{number:int, year:?int, episodeNumbers:int[]}>}
     */
    private function buildSeasonStructure(int $showTraktId, ?int $airedEpisodes): array
    {
        $seasons = $this->trakt->get("/shows/{$showTraktId}/seasons?extended=full,episodes");

        $structure = [
            'airedEpisodes' => $airedEpisodes,
            'seasons' => array_values(array_map(fn ($season) => [
                'number' => $season['number'],
                'year' => isset($season['first_aired']) ? (int) substr($season['first_aired'], 0, 4) : null,
                'episodeNumbers' => array_map(fn ($e) => $e['number'], $season['episodes'] ?? []),
            ], array_filter($seasons, fn ($s) => $s['number'] !== 0))),
        ];

        $this->shows->updateSeasonStructure($showTraktId, $structure);
        return $structure;
    }

    /**
     * Episode titles for the current language -- first from shows.episode_translations, only
     * seasons with missing episodes (newly aired episodes or first-time lookup) are fetched
     * via TMDB (or Trakt fallback for English/no TMDB match) and written back.
     * Titles that have already been assigned practically never change after their initial
     * release -- a manual "sync now" covers the rare correction case.
     *
     * @param array<int, array{number:int, year:?int, episodeNumbers:int[]}> $seasons from season_structure
     * @return array<int, array<int, string>> [season => [episode number => title]]
     */
    private function resolveEpisodeTitles(int $showTraktId, ?int $tmdbId, array $seasons): array
    {
        $cached = $this->shows->getEpisodeTranslations($showTraktId, $this->language);

        $missingSeasons = [];
        foreach ($seasons as $season) {
            $knownEpisodes = $season['episodeNumbers'];
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

    /** @param 'show'|'movie' $itemType */
    public function removeFromWatchlist(string $itemType, int $traktId): void
    {
        $key = $itemType === 'movie' ? 'movies' : 'shows';
        $this->trakt->post('/sync/watchlist/remove', [$key => [['ids' => ['trakt' => $traktId]]]]);
        $this->watchlist->deleteOne($itemType, $traktId);
    }

    /** @param 'show'|'movie' $itemType */
    public function addToWatchlist(string $itemType, int $traktId): void
    {
        // Ensures the local shows/movies row exists even for an item the user just found via
        // search and never had synced before -- without this, watchlist_items would reference
        // a nonexistent row and the item's detail page/library entry would come up empty.
        $itemType === 'movie' ? $this->syncMovie($traktId) : $this->syncShow($traktId);

        $key = $itemType === 'movie' ? 'movies' : 'shows';
        $this->trakt->post('/sync/watchlist', [$key => [['ids' => ['trakt' => $traktId]]]]);
        $this->watchlist->upsertOne($itemType, $traktId, date('Y-m-d H:i:s'));
    }

    /** Movie-only in this app's UI (no whole-show collect button, only per-season --
     *  see collectSeason() below). Metadata sync first, same reasoning as addToWatchlist(). */
    public function addToCollection(int $traktId): void
    {
        $this->syncMovie($traktId);
        $this->trakt->post('/sync/collection', ['movies' => [['ids' => ['trakt' => $traktId]]]]);
        $this->collection->upsertOne('movie', $traktId, 0, date('Y-m-d H:i:s'));
    }

    public function removeFromCollection(int $traktId): void
    {
        $this->trakt->post('/sync/collection/remove', ['movies' => [['ids' => ['trakt' => $traktId]]]]);
        $this->collection->deleteOne('movie', $traktId, 0);
    }

    /** Collects an entire season -- no "episodes" key is Trakt's whole-season shorthand,
     *  exact mirror of markSeasonWatched(). Minimal payload only (no media_type/resolution/
     *  audio -- this app doesn't track physical formats). Unlike markEpisodeWatched(),
     *  Collection has no "cascade prior episodes" concept -- it's a direct boolean per
     *  season, no confirm-dialog interaction needed on the frontend. */
    public function collectSeason(int $showTraktId, int $season): void
    {
        $this->syncShow($showTraktId);
        $this->trakt->post('/sync/collection', [
            'shows' => [['ids' => ['trakt' => $showTraktId], 'seasons' => [['number' => $season]]]],
        ]);
        $this->collection->upsertOne('show', $showTraktId, $season, date('Y-m-d H:i:s'));
    }

    public function uncollectSeason(int $showTraktId, int $season): void
    {
        $this->trakt->post('/sync/collection/remove', [
            'shows' => [['ids' => ['trakt' => $showTraktId], 'seasons' => [['number' => $season]]]],
        ]);
        $this->collection->deleteOne('show', $showTraktId, $season);
    }

    /** Marks a movie as watched on Trakt, syncing its metadata first if it isn't local yet
     *  (e.g. a fresh search result). */
    public function markMovieWatched(int $traktId): void
    {
        $this->syncMovie($traktId);
        $this->trakt->post('/sync/history', ['movies' => [['ids' => ['trakt' => $traktId]]]]);
        $this->movies->markWatched($traktId, date('Y-m-d H:i:s'));
    }

    /** Trakt search across shows and movies, enriched with TMDB posters the same way sync
     *  does -- Trakt itself never returns image URLs. Read-only: no local DB writes, results
     *  only get persisted once the user adds one via addToWatchlist()/markMovieWatched(). */
    public function searchTrakt(string $query): array
    {
        $results = array_values(array_filter(
            $this->trakt->get('/search/movie,show?query=' . urlencode($query) . '&extended=full&limit=30'),
            fn ($r) => in_array($r['type'], ['show', 'movie'], true)
        ));

        $showItems = array_map(fn ($r) => $r['show'], array_filter($results, fn ($r) => $r['type'] === 'show'));
        $movieItems = array_map(fn ($r) => $r['movie'], array_filter($results, fn ($r) => $r['type'] === 'movie'));
        $showTraktIds = array_map(fn ($i) => $i['ids']['trakt'], $showItems);
        $movieTraktIds = array_map(fn ($i) => $i['ids']['trakt'], $movieItems);

        $showDetails = $this->tmdbDetailsFor($showItems, 'tv');
        $movieDetails = $this->tmdbDetailsFor($movieItems, 'movie');
        $watchedShowIds = array_flip($this->progress->watchedShowIds($showTraktIds));
        $watchedMovieIds = array_flip($this->movies->watchedTraktIds($movieTraktIds));
        $onWatchlistShowIds = array_flip($this->watchlist->watchlistedTraktIds('show', $showTraktIds));
        $onWatchlistMovieIds = array_flip($this->watchlist->watchlistedTraktIds('movie', $movieTraktIds));

        return array_map(fn ($r) => $this->mapDiscoverItem(
            $r[$r['type']],
            $r['type'],
            $r['type'] === 'show' ? $showDetails : $movieDetails,
            $r['type'] === 'show' ? $watchedShowIds : $watchedMovieIds,
            $r['type'] === 'show' ? $onWatchlistShowIds : $onWatchlistMovieIds
        ), $results);
    }

    /** Personalized recommendations, trending shows and popular movies -- same card shape as
     *  searchTrakt(), used as the Search page's browse-first empty state. */
    public function recommendedShows(): array
    {
        return $this->mapDiscoverList($this->trakt->get('/recommendations/shows?limit=30&extended=full'), 'show');
    }

    public function recommendedMovies(): array
    {
        return $this->mapDiscoverList($this->trakt->get('/recommendations/movies?limit=30&extended=full'), 'movie');
    }

    /** Connected Trakt account info for display in Settings -- no social/follower data. */
    public function getUserProfile(): array
    {
        $user = $this->trakt->get('/users/settings')['user'] ?? [];
        return [
            'username' => $user['username'] ?? null,
            'name' => $user['name'] ?? null,
            'avatar' => $user['images']['avatar']['full'] ?? null,
            'joinedAt' => $user['joined_at'] ?? null,
        ];
    }

    public function trendingShows(): array
    {
        $entries = $this->trakt->get('/shows/trending?limit=30&extended=full');
        return $this->mapDiscoverList(array_map(fn ($e) => $e['show'], $entries), 'show');
    }

    public function popularMovies(): array
    {
        return $this->mapDiscoverList($this->trakt->get('/movies/popular?limit=30&extended=full'), 'movie');
    }

    /** "More like this" row for the show/movie detail pages. */
    public function relatedShows(int $traktId): array
    {
        return $this->mapDiscoverList($this->trakt->get("/shows/{$traktId}/related?limit=10&extended=full"), 'show');
    }

    public function relatedMovies(int $traktId): array
    {
        return $this->mapDiscoverList($this->trakt->get("/movies/{$traktId}/related?limit=10&extended=full"), 'movie');
    }

    /** Live, non-persisting show detail for an item not yet in the local library (e.g. a
     *  Recommendations/Trending/Related click) -- same shape as ShowRepository::findOne(),
     *  but nothing is written to the DB. Only an explicit watchlist/watch action persists it
     *  (see addToWatchlist()/markEpisodeWatched(), both already sync-on-demand). */
    public function previewShow(int $traktId): ?array
    {
        try {
            $show = $this->trakt->get("/shows/{$traktId}?extended=full");
        } catch (Throwable) {
            return null;
        }

        $tmdbId = $show['ids']['tmdb'] ?? null;
        $details = $tmdbId ? $this->tmdb->getManyDetails([$tmdbId], 'tv', Languages::locale($this->language)) : [];
        $detail = $tmdbId ? ($details[$tmdbId] ?? null) : null;
        $useTranslation = $detail !== null && $this->language !== 'en';

        return [
            'id' => $traktId,
            'slug' => $show['ids']['slug'],
            'title' => ($useTranslation && $detail['title'] !== '') ? $detail['title'] : $show['title'],
            'year' => $show['year'] ?? null,
            'overview' => ($useTranslation && $detail['overview'] !== '') ? $detail['overview'] : ($show['overview'] ?? null),
            'status' => $show['status'] ?? null,
            'network' => $show['network'] ?? null,
            'runtime' => $show['runtime'] ?? null,
            'genres' => $show['genres'] ?? [],
            'posterUrl' => $detail['poster_url'] ?? null,
            'backdropUrl' => $detail['backdrop_url'] ?? null,
            'airedEpisodes' => $show['aired_episodes'] ?? null,
            'certification' => $show['certification'] ?? null,
            'rating' => null,
            'tmdbId' => $tmdbId,
            'onWatchlist' => $this->watchlist->watchlistedTraktIds('show', [$traktId]) !== [],
            'progress' => null,
            'inLibrary' => false,
        ];
    }

    /** Movie counterpart of previewShow(). */
    public function previewMovie(int $traktId): ?array
    {
        try {
            $movie = $this->trakt->get("/movies/{$traktId}?extended=full");
        } catch (Throwable) {
            return null;
        }

        $tmdbId = $movie['ids']['tmdb'] ?? null;
        $details = $tmdbId ? $this->tmdb->getManyDetails([$tmdbId], 'movie', Languages::locale($this->language)) : [];
        $detail = $tmdbId ? ($details[$tmdbId] ?? null) : null;
        $useTranslation = $detail !== null && $this->language !== 'en';

        return [
            'id' => $traktId,
            'slug' => $movie['ids']['slug'],
            'title' => ($useTranslation && $detail['title'] !== '') ? $detail['title'] : $movie['title'],
            'year' => $movie['year'] ?? null,
            'overview' => ($useTranslation && $detail['overview'] !== '') ? $detail['overview'] : ($movie['overview'] ?? null),
            'status' => $movie['status'] ?? null,
            'genres' => $movie['genres'] ?? [],
            'posterUrl' => $detail['poster_url'] ?? null,
            'backdropUrl' => $detail['backdrop_url'] ?? null,
            'runtime' => $movie['runtime'] ?? null,
            'released' => $movie['released'] ?? null,
            'certification' => $movie['certification'] ?? null,
            'rating' => null,
            'watchedAt' => null,
            'onWatchlist' => $this->watchlist->watchlistedTraktIds('movie', [$traktId]) !== [],
            'inLibrary' => false,
        ];
    }

    /** Batched TMDB poster/translation lookup shared by searchTrakt() and the discovery
     *  endpoints below. @param 'tv'|'movie' $mediaType */
    private function tmdbDetailsFor(array $items, string $mediaType): array
    {
        $tmdbIds = array_values(array_filter(array_map(fn ($i) => $i['ids']['tmdb'] ?? null, $items)));
        return $tmdbIds !== [] ? $this->tmdb->getManyDetails($tmdbIds, $mediaType, Languages::locale($this->language)) : [];
    }

    /** TMDB-enriches + watched-flags a flat list of same-type raw Trakt show/movie objects
     *  into the SearchResult card shape. @param 'show'|'movie' $type */
    private function mapDiscoverList(array $items, string $type): array
    {
        $details = $this->tmdbDetailsFor($items, $type === 'show' ? 'tv' : 'movie');
        $traktIds = array_map(fn ($i) => $i['ids']['trakt'], $items);
        // "Already watched"/"already on watchlist" are judged from the local DB (same source
        // the rest of the app trusts), not a live Trakt call per result -- consistent with how
        // the library elsewhere never live-queries watched state for movies, and keeps a
        // 30-result list from firing dozens of extra requests.
        $watchedIds = array_flip($type === 'show' ? $this->progress->watchedShowIds($traktIds) : $this->movies->watchedTraktIds($traktIds));
        $onWatchlistIds = array_flip($this->watchlist->watchlistedTraktIds($type, $traktIds));

        return array_map(fn ($item) => $this->mapDiscoverItem($item, $type, $details, $watchedIds, $onWatchlistIds), $items);
    }

    /** @param 'show'|'movie' $type */
    private function mapDiscoverItem(array $item, string $type, array $details, array $watchedIds, array $onWatchlistIds): array
    {
        $traktId = $item['ids']['trakt'];
        $tmdbId = $item['ids']['tmdb'] ?? null;
        $detail = $tmdbId ? ($details[$tmdbId] ?? null) : null;
        // Mirrors applyShowTranslation()/applyMovieTranslation(): only substitute the
        // TMDB-localized text when it's actually a non-English language and non-empty --
        // otherwise keep Trakt's original (guaranteed) English title/overview.
        $useTranslation = $detail !== null && $this->language !== 'en';
        return [
            'type' => $type,
            'traktId' => $traktId,
            'title' => ($useTranslation && $detail['title'] !== '') ? $detail['title'] : $item['title'],
            'year' => $item['year'] ?? null,
            'overview' => ($useTranslation && $detail['overview'] !== '') ? $detail['overview'] : ($item['overview'] ?? null),
            'genres' => $item['genres'] ?? [],
            'posterUrl' => $detail['poster_url'] ?? null,
            'watched' => isset($watchedIds[$traktId]),
            'onWatchlist' => isset($onWatchlistIds[$traktId]),
        ];
    }

    /** "Cancel" a show -- hides it from Trakt's watch-progress calculation (what powers
     *  Continue Watching / up-next), without touching watch history or collection. Section
     *  'progress_watched' is Trakt's dedicated mechanism for exactly this. */
    public function hideShow(int $traktShowId): void
    {
        $this->trakt->post('/users/hidden/progress_watched', ['shows' => [['ids' => ['trakt' => $traktShowId]]]]);
        $this->progress->setHidden($traktShowId, true);
    }

    public function unhideShow(int $traktShowId): void
    {
        $this->trakt->post('/users/hidden/progress_watched/remove', ['shows' => [['ids' => ['trakt' => $traktShowId]]]]);
        $this->progress->setHidden($traktShowId, false);
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

    /** Resyncs metadata for a single movie -- syncShow()'s movie counterpart. */
    public function syncMovie(int $traktId): void
    {
        $movie = $this->trakt->get("/movies/{$traktId}?extended=full");
        $tmdbId = $movie['ids']['tmdb'] ?? null;
        $details = $tmdbId ? $this->tmdb->getManyDetails([$tmdbId], 'movie', Languages::locale($this->language)) : [];

        $this->movies->upsert($this->mapMovie($movie));
        $this->applyMovieTranslation($movie['ids']['trakt'], $tmdbId, $details);
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
            $this->movies->upsert($this->mapMovie($movie, $entry['last_watched_at'] ?? null));
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

    /** Items marked "to watch" but not yet started (GET /sync/watchlist) -- shows and movies
     *  come back mixed in one response. Metadata is upserted via the exact same mapShow()/
     *  mapMovie()/applyXTranslation() pipeline as the watched-sync paths, so a watchlist item
     *  is fully detail-page-ready (poster, overview, episodes) even if never watched. */
    private function syncWatchlist(): int
    {
        $entries = $this->trakt->get('/sync/watchlist');
        if ($entries === []) {
            $this->watchlist->replaceAll(null, []);
            return 0;
        }

        $showEntries = array_values(array_filter($entries, fn ($e) => $e['type'] === 'show'));
        $movieEntries = array_values(array_filter($entries, fn ($e) => $e['type'] === 'movie'));
        $rows = [];

        if ($showEntries !== []) {
            $traktIds = array_map(fn ($e) => $e['show']['ids']['trakt'], $showEntries);
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

            foreach ($showEntries as $entry) {
                $traktId = $entry['show']['ids']['trakt'];
                // Metadata fetch failed: skip -- a watchlist_items row referencing a show
                // with no local `shows` row would be undetailable on its own detail page.
                if (!isset($showsById[$traktId])) {
                    continue;
                }
                $show = $showsById[$traktId];
                $this->shows->upsert($this->mapShow($show));
                $this->applyShowTranslation($traktId, $show['ids']['tmdb'] ?? null, $details);
                $rows[] = [
                    'item_type' => 'show',
                    'item_trakt_id' => $traktId,
                    'listed_at' => self::toDatetime($entry['listed_at'] ?? null) ?? date('Y-m-d H:i:s'),
                ];
            }
        }

        if ($movieEntries !== []) {
            $tmdbIds = array_values(array_filter(array_map(
                fn ($e) => $e['movie']['ids']['tmdb'] ?? null,
                $movieEntries
            )));
            $details = $this->tmdb->getManyDetails($tmdbIds, 'movie', Languages::locale($this->language));

            foreach ($movieEntries as $entry) {
                $movie = $entry['movie'];
                // watched_at intentionally omitted (defaults to null) -- upsert()'s COALESCE
                // guard means this never clobbers an already-watched movie's watched_at.
                $this->movies->upsert($this->mapMovie($movie));
                $this->applyMovieTranslation($movie['ids']['trakt'], $movie['ids']['tmdb'] ?? null, $details);
                $rows[] = [
                    'item_type' => 'movie',
                    'item_trakt_id' => $movie['ids']['trakt'],
                    'listed_at' => self::toDatetime($entry['listed_at'] ?? null) ?? date('Y-m-d H:i:s'),
                ];
            }
        }

        $this->watchlist->replaceAll(null, $rows);
        return count($rows);
    }

    /** Items marked "owned" via Trakt's Collection feature (GET /sync/collection/movies,
     *  GET /sync/collection/shows) -- these are two SEPARATE endpoints, unlike
     *  /sync/watchlist's single mixed response. Only show+season presence is parsed --
     *  media_type/resolution/audio metadata in the response is discarded, this app doesn't
     *  track physical formats. */
    private function syncCollection(): int
    {
        $movieRows = [];
        $movieEntries = $this->trakt->get('/sync/collection/movies?extended=full');
        if ($movieEntries !== []) {
            $tmdbIds = array_values(array_filter(array_map(fn ($e) => $e['movie']['ids']['tmdb'] ?? null, $movieEntries)));
            $details = $this->tmdb->getManyDetails($tmdbIds, 'movie', Languages::locale($this->language));
            foreach ($movieEntries as $entry) {
                $movie = $entry['movie'];
                // watched_at intentionally omitted (defaults to null), same reasoning as
                // syncWatchlist() -- upsert()'s COALESCE guard means this never clobbers an
                // already-watched movie's watched_at.
                $this->movies->upsert($this->mapMovie($movie));
                $this->applyMovieTranslation($movie['ids']['trakt'], $movie['ids']['tmdb'] ?? null, $details);
                $movieRows[] = [
                    'item_type' => 'movie',
                    'item_trakt_id' => $movie['ids']['trakt'],
                    'season_number' => 0,
                    'collected_at' => self::toDatetime($entry['collected_at'] ?? null) ?? date('Y-m-d H:i:s'),
                ];
            }
        }
        $this->collection->replaceAll('movie', $movieRows);

        $showRows = [];
        $showEntries = $this->trakt->get('/sync/collection/shows?extended=full');
        if ($showEntries !== []) {
            $traktIds = array_map(fn ($e) => $e['show']['ids']['trakt'], $showEntries);
            $metaResults = $this->trakt->getMany(array_map(fn ($id) => "/shows/{$id}?extended=full", $traktIds));
            $showsById = [];
            foreach ($metaResults as $s) {
                $showsById[$s['ids']['trakt']] = $s;
            }

            $tmdbIds = array_values(array_filter(array_map(fn ($s) => $s['ids']['tmdb'] ?? null, $showsById)));
            $details = $this->tmdb->getManyDetails($tmdbIds, 'tv', Languages::locale($this->language));

            foreach ($showEntries as $entry) {
                $traktId = $entry['show']['ids']['trakt'];
                // Metadata fetch failed: skip, same reasoning as syncWatchlist().
                if (!isset($showsById[$traktId])) {
                    continue;
                }
                $this->shows->upsert($this->mapShow($showsById[$traktId]));
                $this->applyShowTranslation($traktId, $showsById[$traktId]['ids']['tmdb'] ?? null, $details);
                foreach ($entry['seasons'] ?? [] as $season) {
                    // Presence heuristic: a season counts as "collected" if it has >=1 episode
                    // entry, not a strict count match against season_structure. This app only
                    // ever WRITES whole-season collection, so the only way this could diverge
                    // is a partial season collected directly via another Trakt client.
                    if (($season['episodes'] ?? []) === []) {
                        continue;
                    }
                    $showRows[] = [
                        'item_type' => 'show',
                        'item_trakt_id' => $traktId,
                        'season_number' => $season['number'],
                        // Trakt gives per-episode collected_at, not per-season -- sync time is used instead.
                        'collected_at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
        }
        $this->collection->replaceAll('show', $showRows);

        return count($movieRows) + count($showRows);
    }

    /** Reconciles local hidden flags with Trakt's 'progress_watched' hidden list (source of
     *  truth) -- a show unhidden directly on trakt.tv also un-hides here on the next sync.
     *  This endpoint is paginated per Trakt's docs, but a personal watchlist-sized hidden
     *  list comfortably fits in one page -- no pagination handling needed. */
    private function syncHiddenShows(): int
    {
        $entries = $this->trakt->get('/users/hidden/progress_watched?type=show&limit=100');
        $ids = array_map(fn ($entry) => $entry['show']['ids']['trakt'], $entries);
        $this->progress->replaceHiddenFlags($ids);
        return count($ids);
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

    private function mapMovie(array $movie, ?string $watchedAtIso = null): array
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
            'watched_at' => self::toDatetime($watchedAtIso),
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
