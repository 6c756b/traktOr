<?php

namespace TraktOr\Auth;

use TraktOr\Db\Database;
use TraktOr\Http\Response;
use TraktOr\Support\Config;

final class AppAuth
{
    public const LOGIN_OK = 'ok';
    public const LOGIN_WRONG_PASSWORD = 'wrong_password';
    public const LOGIN_RATE_LIMITED = 'rate_limited';

    /** From this failed attempt onward, an increasing lockout applies (2^(n-THRESHOLD) minutes). */
    private const THROTTLE_THRESHOLD = 5;

    public static function login(string $password): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $pdo = Database::pdo();

        $stmt = $pdo->prepare('SELECT attempts, locked_until FROM login_attempts WHERE ip_address = :ip');
        $stmt->execute(['ip' => $ip]);
        $row = $stmt->fetch();

        if ($row && $row['locked_until'] !== null && strtotime($row['locked_until']) > time()) {
            return self::LOGIN_RATE_LIMITED;
        }

        $hash = Config::load()['app']['password_hash'] ?? '';

        if ($hash === '' || !password_verify($password, $hash)) {
            $attempts = ($row['attempts'] ?? 0) + 1;
            $lockedUntil = null;
            if ($attempts >= self::THROTTLE_THRESHOLD) {
                $minutes = 2 ** ($attempts - self::THROTTLE_THRESHOLD);
                $lockedUntil = date('Y-m-d H:i:s', time() + $minutes * 60);
            }
            $pdo->prepare(
                'INSERT INTO login_attempts (ip_address, attempts, locked_until) VALUES (:ip, :attempts, :locked_until)
                 ON DUPLICATE KEY UPDATE attempts = :attempts, locked_until = :locked_until'
            )->execute(['ip' => $ip, 'attempts' => $attempts, 'locked_until' => $lockedUntil]);

            return self::LOGIN_WRONG_PASSWORD;
        }

        $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip')->execute(['ip' => $ip]);

        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        return self::LOGIN_OK;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function isAuthenticated(): bool
    {
        return $_SESSION['authenticated'] ?? false;
    }

    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            Response::error(401, 'not_authenticated');
        }
    }
}
