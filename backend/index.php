<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TraktOr\Auth\AppAuth;
use TraktOr\Auth\TraktOAuth;
use TraktOr\Db\Repositories\ListRepository;
use TraktOr\Db\Repositories\MovieRepository;
use TraktOr\Db\Repositories\SettingsRepository;
use TraktOr\Db\Repositories\ShowRepository;
use TraktOr\Db\Repositories\SyncStateRepository;
use TraktOr\Http\Request;
use TraktOr\Http\Router;
use TraktOr\Http\Response;
use TraktOr\Sync\SyncService;
use TraktOr\Sync\ContinueWatchingBuilder;
use TraktOr\Support\Languages;
use TraktOr\Support\Version;
use TraktOr\Trakt\TraktClient;

function parseLibraryFilters(Request $request): array
{
    $filters = [];
    $q = $request->query;

    if (!empty($q['genres'])) {
        $filters['genres'] = array_filter(array_map('trim', explode(',', $q['genres'])));
    }
    if (!empty($q['statuses'])) {
        $filters['statuses'] = array_filter(array_map('trim', explode(',', $q['statuses'])));
    }
    if (isset($q['year_min']) && $q['year_min'] !== '') {
        $filters['year_min'] = (int) $q['year_min'];
    }
    if (isset($q['year_max']) && $q['year_max'] !== '') {
        $filters['year_max'] = (int) $q['year_max'];
    }
    if (isset($q['rating_min']) && $q['rating_min'] !== '') {
        $filters['rating_min'] = (int) $q['rating_min'];
    }
    if (isset($q['list_id']) && $q['list_id'] !== '') {
        $filters['list_id'] = (int) $q['list_id'];
    }
    if (!empty($q['search'])) {
        $filters['search'] = $q['search'];
    }

    return $filters;
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

$configFile = __DIR__ . '/config/config.php';
$config = is_file($configFile) ? require $configFile : [];
$frontendUrl = $config['app']['frontend_url'] ?? '';

$router = new Router();

$router->get('/ping', function () {
    Response::json(['status' => 'ok', 'message' => 'Der Traktor läuft.', 'version' => Version::current()]);
});

$router->post('/auth/login', function (Request $request) {
    $password = $request->json()['password'] ?? '';
    $result = AppAuth::login($password);
    if ($result === AppAuth::LOGIN_RATE_LIMITED) {
        Response::error(429, 'rate_limited');
    }
    if ($result !== AppAuth::LOGIN_OK) {
        Response::error(401, 'wrong_password');
    }
    Response::json(['authenticated' => true]);
});

$router->post('/auth/logout', function () {
    AppAuth::logout();
    Response::noContent();
});

$router->get('/auth/session', function () {
    Response::json([
        'authenticated' => AppAuth::isAuthenticated(),
        'traktConnected' => AppAuth::isAuthenticated() && (new TraktClient())->isConnected(),
    ]);
});

$router->get('/auth/trakt/start', function () {
    AppAuth::requireAuth();
    Response::redirect(TraktOAuth::startUrl());
});

$router->get('/auth/trakt/callback', function (Request $request) use ($frontendUrl) {
    $code = $request->query['code'] ?? '';
    $state = $request->query['state'] ?? '';
    TraktOAuth::handleCallback($code, $state);
    Response::redirect($frontendUrl . '/settings?connected=1');
});

$router->post('/sync/full', function () {
    AppAuth::requireAuth();
    $result = (new SyncService())->fullSync();
    Response::json($result);
});

$router->get('/sync/status', function () {
    AppAuth::requireAuth();
    Response::json((new SyncStateRepository())->all());
});

$router->get('/continue-watching', function (Request $request) {
    AppAuth::requireAuth();
    $sort = $request->query['sort'] ?? 'new';
    $builder = new ContinueWatchingBuilder();
    Response::json([
        'items' => $builder->build($sort),
        'stale' => $builder->isStale(),
    ]);
});

$router->post('/watch/episode', function (Request $request) {
    AppAuth::requireAuth();
    $body = $request->json();
    $showId = (int) ($body['showId'] ?? 0);
    $season = (int) ($body['season'] ?? -1);
    $number = (int) ($body['number'] ?? -1);
    if ($showId <= 0 || $season < 0 || $number < 0) {
        Response::error(400, 'missing_fields');
    }

    (new SyncService())->markEpisodeWatched($showId, $season, $number);
    Response::json(['item' => (new ContinueWatchingBuilder())->buildOne($showId)]);
});

$router->post('/unwatch/episode', function (Request $request) {
    AppAuth::requireAuth();
    $body = $request->json();
    $showId = (int) ($body['showId'] ?? 0);
    $season = (int) ($body['season'] ?? -1);
    $number = (int) ($body['number'] ?? -1);
    if ($showId <= 0 || $season < 0 || $number < 0) {
        Response::error(400, 'missing_fields');
    }

    (new SyncService())->unmarkEpisodeWatched($showId, $season, $number);
    Response::json(['item' => (new ContinueWatchingBuilder())->buildOne($showId)]);
});

$router->post('/watch/season', function (Request $request) {
    AppAuth::requireAuth();
    $body = $request->json();
    $showId = (int) ($body['showId'] ?? 0);
    $season = (int) ($body['season'] ?? -1);
    if ($showId <= 0 || $season < 0) {
        Response::error(400, 'missing_fields');
    }

    (new SyncService())->markSeasonWatched($showId, $season);
    Response::json(['item' => (new ContinueWatchingBuilder())->buildOne($showId)]);
});

$router->get('/shows/:id/episodes', function (Request $request, array $params) {
    AppAuth::requireAuth();
    Response::json((new SyncService())->getEpisodes((int) $params['id']));
});

$router->post('/rate', function (Request $request) {
    AppAuth::requireAuth();
    $body = $request->json();
    $itemType = $body['itemType'] ?? '';
    $id = (int) ($body['id'] ?? 0);
    $rating = (int) ($body['rating'] ?? 0);
    if (!in_array($itemType, ['show', 'movie'], true) || $id <= 0 || $rating < 1 || $rating > 10) {
        Response::error(400, 'missing_fields');
    }

    (new SyncService())->rateItem($itemType, $id, $rating);
    Response::json(['rating' => $rating]);
});

$router->delete('/rate/:itemType/:id', function (Request $request, array $params) {
    AppAuth::requireAuth();
    $itemType = $params['itemType'];
    $id = (int) $params['id'];
    if (!in_array($itemType, ['show', 'movie'], true) || $id <= 0) {
        Response::error(400, 'invalid_item_type');
    }

    (new SyncService())->unrateItem($itemType, $id);
    Response::noContent();
});

$router->get('/shows', function (Request $request) {
    AppAuth::requireAuth();
    $filters = parseLibraryFilters($request);
    $sort = $request->query['sort'] ?? 'title';
    $dir = $request->query['dir'] ?? '';
    $language = (new SettingsRepository())->getLanguage();
    Response::json((new ShowRepository())->search($filters, $sort, $dir, $language));
});

$router->get('/shows/:id', function (Request $request, array $params) {
    AppAuth::requireAuth();
    $language = (new SettingsRepository())->getLanguage();
    $show = (new ShowRepository())->findOne((int) $params['id'], $language);
    if (!$show) {
        Response::error(404, 'show_not_found');
    }
    Response::json($show);
});

$router->get('/movies', function (Request $request) {
    AppAuth::requireAuth();
    $filters = parseLibraryFilters($request);
    $sort = $request->query['sort'] ?? 'title';
    $dir = $request->query['dir'] ?? '';
    $language = (new SettingsRepository())->getLanguage();
    Response::json((new MovieRepository())->search($filters, $sort, $dir, $language));
});

$router->get('/movies/:id', function (Request $request, array $params) {
    AppAuth::requireAuth();
    $language = (new SettingsRepository())->getLanguage();
    $movie = (new MovieRepository())->findOne((int) $params['id'], $language);
    if (!$movie) {
        Response::error(404, 'movie_not_found');
    }
    Response::json($movie);
});

$router->get('/genres', function (Request $request) {
    AppAuth::requireAuth();
    $type = $request->query['type'] ?? 'shows';
    $genres = $type === 'movies' ? (new MovieRepository())->distinctGenres() : (new ShowRepository())->distinctGenres();
    Response::json($genres);
});

$router->get('/lists', function () {
    AppAuth::requireAuth();
    Response::json((new ListRepository())->all());
});

$router->get('/settings', function () {
    AppAuth::requireAuth();
    $settings = new SettingsRepository();
    Response::json([
        'language' => $settings->getLanguage(),
        'availableLanguages' => Languages::all(),
        'theme' => $settings->getTheme(),
    ]);
});

$router->post('/settings', function (Request $request) {
    AppAuth::requireAuth();
    $body = $request->json();
    $settings = new SettingsRepository();

    if (array_key_exists('language', $body)) {
        if (!Languages::isSupported($body['language'])) {
            Response::error(400, 'unsupported_language');
        }
        $settings->setLanguage($body['language']);

        // Syncing for the new language (fetching missing translations) no longer runs
        // inline here -- with many shows this is a timeout risk on shared hosting with a
        // tight max_execution_time. The frontend triggers POST /sync/full separately and shows
        // progress via the existing sync UI status.
    }

    if (array_key_exists('theme', $body)) {
        $theme = $body['theme'];
        if ($theme !== null && !in_array($theme, ['light', 'dark'], true)) {
            Response::error(400, 'invalid_theme');
        }
        $settings->setTheme($theme);
    }

    Response::json([
        'language' => $settings->getLanguage(),
        'theme' => $settings->getTheme(),
    ]);
});

try {
    $router->dispatch(new Request());
} catch (\Throwable $e) {
    Response::error(500, 'server_error', $e->getMessage());
}
