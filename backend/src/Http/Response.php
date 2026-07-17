<?php

namespace TraktOr\Http;

final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /** $code is a stable, language-neutral error code (the frontend translates it via i18n). */
    public static function error(int $status, string $code, ?string $logMessage = null): never
    {
        if ($logMessage !== null || $status >= 500) {
            error_log($logMessage ?? $code);
        }
        self::json(['error' => $code], $status);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
