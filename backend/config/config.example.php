<?php

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'traktor',
        'user' => 'traktor',
        'pass' => 'changeme',
    ],
    'trakt' => [
        'client_id' => '',
        'client_secret' => '',
        // Full path including any subfolder (e.g. when hosting under
        // https://domain.tld/traktor/ this would be https://domain.tld/traktor/api/auth/trakt/callback)
        // -- must be stored exactly like this as the redirect URI in the Trakt app settings too.
        'redirect_uri' => 'https://example.com/api/auth/trakt/callback',
    ],
    'tmdb' => [
        // API key (v3 auth) from https://www.themoviedb.org/settings/api
        'api_key' => '',
    ],
    'app' => [
        // generate with: php -r "echo password_hash('your-password', PASSWORD_BCRYPT), PHP_EOL;"
        'password_hash' => '',
        // Always set the full frontend URL including any subfolder, do NOT leave it
        // empty -- used as the redirect target prefix after Trakt login
        // (frontend_url . '/settings?connected=1'). Examples:
        // Root hosting:    https://domain.tld
        // Subfolder:       https://domain.tld/traktor
        // Local (Vite dev server): http://localhost:5173
        'frontend_url' => '',
    ],
    'cron' => [
        // Timezone for the night window in NightlySyncJob (see backend/cron.php). The
        // host's own PHP default (php.ini date.timezone) isn't known from outside and
        // doesn't have to match "your" night -- so set it explicitly here.
        // PHP timezone list: https://www.php.net/manual/en/timezones.php
        'timezone' => 'Europe/Berlin',
    ],
];
