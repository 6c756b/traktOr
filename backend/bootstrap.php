<?php

declare(strict_types=1);

// PHP's default timezone (php.ini date.timezone) depends on the host and is not
// guaranteed to be UTC from here. However, all strtotime()/time() comparisons against
// DB timestamps (SyncStateRepository::isStale(), AppAuth's login lockout) assume that PHP
// and MySQL (see Database::pdo(), which explicitly sets UTC there) operate on the same time
// system -- so we enforce it centrally here instead of relying on the server configuration.
date_default_timezone_set('UTC');

spl_autoload_register(function (string $class): void {
    $prefix = 'TraktOr\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
