<?php

namespace TraktOr\Support;

use RuntimeException;

final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @return array<string, mixed> */
    public static function load(): array
    {
        if (self::$config === null) {
            $file = __DIR__ . '/../../config/config.php';
            if (!is_file($file)) {
                throw new RuntimeException(
                    'config/config.php is missing. Copy config/config.example.php to config/config.php '
                    . 'and fill it in -- it is deliberately never included in deploy uploads/syncs.'
                );
            }
            self::$config = require $file;
        }
        return self::$config;
    }
}
