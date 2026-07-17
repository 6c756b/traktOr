<?php

namespace TraktOr\Db;

use PDO;
use TraktOr\Support\Config;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $db = Config::load()['db'];
            $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // Depending on the host, MySQL runs with the server's system timezone instead of
            // UTC -- without explicitly aligning it here, NOW() would drift from PHP's
            // time()/date() (always UTC), which would skew every comparison between a DB
            // timestamp and PHP time (e.g. SyncStateRepository::isStale(), AppAuth's login lockout).
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
        return self::$pdo;
    }
}
