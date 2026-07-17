<?php

namespace TraktOr\Auth;

use RuntimeException;
use TraktOr\Trakt\TraktClient;

final class TraktOAuth
{
    public static function startUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['trakt_oauth_state'] = $state;
        return (new TraktClient())->authorizeUrl($state);
    }

    public static function handleCallback(string $code, string $state): void
    {
        $expected = $_SESSION['trakt_oauth_state'] ?? null;
        unset($_SESSION['trakt_oauth_state']);

        if ($expected === null || !hash_equals($expected, $state)) {
            throw new RuntimeException('invalid_oauth_state');
        }

        (new TraktClient())->exchangeCode($code);
    }
}
