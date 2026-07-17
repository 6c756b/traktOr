<?php

namespace TraktOr\Trakt;

use RuntimeException;
use TraktOr\Db\Database;
use TraktOr\Support\Config;

final class TraktClient
{
    private const API_BASE = 'https://api.trakt.tv';
    // Only for the browser redirect to the login/consent page -- for
    // server-side requests (token exchange) ALWAYS use API_BASE,
    // trakt.tv itself has Cloudflare bot protection that blocks curl requests.
    private const AUTHORIZE_BASE = 'https://trakt.tv';

    private array $config;

    public function __construct()
    {
        $this->config = Config::load();
    }

    public function authorizeUrl(string $state): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config['trakt']['client_id'],
            'redirect_uri' => $this->config['trakt']['redirect_uri'],
            'state' => $state,
        ]);
        return self::AUTHORIZE_BASE . '/oauth/authorize?' . $params;
    }

    public function exchangeCode(string $code): array
    {
        $tokens = $this->postOAuth('/oauth/token', [
            'code' => $code,
            'client_id' => $this->config['trakt']['client_id'],
            'client_secret' => $this->config['trakt']['client_secret'],
            'redirect_uri' => $this->config['trakt']['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]);
        $this->storeTokens($tokens);
        return $tokens;
    }

    private function refreshTokens(string $refreshToken): array
    {
        $tokens = $this->postOAuth('/oauth/token', [
            'refresh_token' => $refreshToken,
            'client_id' => $this->config['trakt']['client_id'],
            'client_secret' => $this->config['trakt']['client_secret'],
            'redirect_uri' => $this->config['trakt']['redirect_uri'],
            'grant_type' => 'refresh_token',
        ]);
        $this->storeTokens($tokens);
        return $tokens;
    }

    private function storeTokens(array $tokens): void
    {
        $expiresAt = date('Y-m-d H:i:s', $tokens['created_at'] + $tokens['expires_in']);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO trakt_tokens (id, access_token, refresh_token, expires_at, scope)
             VALUES (1, :access_token, :refresh_token, :expires_at, :scope)
             ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                expires_at = VALUES(expires_at),
                scope = VALUES(scope)'
        );
        $stmt->execute([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => $expiresAt,
            'scope' => $tokens['scope'] ?? null,
        ]);
    }

    public function isConnected(): bool
    {
        $stmt = Database::pdo()->query('SELECT COUNT(*) FROM trakt_tokens WHERE id = 1');
        return (int) $stmt->fetchColumn() > 0;
    }

    private function fetchTokenRow(): array|false
    {
        return Database::pdo()
            ->query('SELECT access_token, refresh_token, expires_at FROM trakt_tokens WHERE id = 1')
            ->fetch();
    }

    private static function isFresh(array $row): bool
    {
        return strtotime($row['expires_at']) - time() > 300;
    }

    private function ensureFreshToken(): string
    {
        $row = $this->fetchTokenRow();
        if (!$row) {
            throw new RuntimeException('Trakt ist nicht verbunden.');
        }

        if (self::isFresh($row)) {
            return $row['access_token'];
        }

        // Named lock prevents two parallel requests from refreshing at the same time with
        // the same (by then already rotated) refresh token -- Trakt rotates refresh tokens,
        // a duplicate refresh could cost the second request its connection.
        $pdo = Database::pdo();
        $pdo->query("SELECT GET_LOCK('trakt_token_refresh', 10)");
        try {
            $row = $this->fetchTokenRow();
            if ($row && self::isFresh($row)) {
                return $row['access_token'];
            }

            $tokens = $this->refreshTokens($row['refresh_token']);
            return $tokens['access_token'];
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('trakt_token_refresh')");
        }
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * Runs multiple GET requests in parallel (curl_multi). Returns [path => decoded body];
     * paths that still fail after a retry are missing from the result.
     *
     * @param string[] $paths
     * @return array<string, array>
     */
    public function getMany(array $paths, int $concurrency = 8): array
    {
        $accessToken = $this->ensureFreshToken();
        $results = [];
        $retry = [];

        foreach (array_chunk($paths, $concurrency) as $batch) {
            $failed = $this->runBatch($batch, $accessToken, $results);
            array_push($retry, ...$failed);
        }

        if ($retry !== []) {
            usleep(500_000);
            foreach (array_chunk($retry, $concurrency) as $batch) {
                $this->runBatch($batch, $accessToken, $results);
            }
        }

        return $results;
    }

    /** @return string[] Paths that failed in this batch (candidates for retry) */
    private function runBatch(array $batch, string $accessToken, array &$results): array
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($batch as $path) {
            $ch = curl_init(self::API_BASE . $path);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => 'TraktOr/1.0',
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'trakt-api-version: 2',
                    'trakt-api-key: ' . $this->config['trakt']['client_id'],
                    'Authorization: Bearer ' . $accessToken,
                ],
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$path] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if (curl_multi_select($multi) === -1) {
                usleep(10_000);
            }
        } while ($running > 0);

        $failed = [];
        foreach ($handles as $path => $ch) {
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body = curl_multi_getcontent($ch);
            // $status stays 0 on network errors (timeout/DNS/TLS) -- treat this like a
            // failed request (retry candidate), don't count it as a success.
            if ($status >= 200 && $status < 300) {
                $results[$path] = json_decode($body, true) ?? [];
            } else {
                $failed[] = $path;
            }
            curl_multi_remove_handle($multi, $ch);
        }
        curl_multi_close($multi);

        return $failed;
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $accessToken = $this->ensureFreshToken();

        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'TraktOr/1.0',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'trakt-api-version: 2',
                'trakt-api-key: ' . $this->config['trakt']['client_id'],
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException("Trakt-Netzwerkfehler bei {$path}: " . curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($status >= 400) {
            throw new RuntimeException("Trakt-API-Fehler ({$status}) bei {$path}: {$response}");
        }

        return $response ? (json_decode($response, true) ?? []) : [];
    }

    private function postOAuth(string $path, array $body): array
    {
        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'TraktOr/1.0',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException("Trakt-OAuth-Netzwerkfehler: " . curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($status >= 400) {
            throw new RuntimeException("Trakt-OAuth-Fehler ({$status}): {$response}");
        }

        $data = json_decode($response, true);
        $data['created_at'] = $data['created_at'] ?? time();
        return $data;
    }
}
