<?php

namespace TraktOr\Http;

final class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly array $query;
    private array $jsonBody;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        // Segment-based instead of anchor-based: works regardless of whether the app
        // sits at the domain root or in a subfolder (e.g. /traktor/api/... instead of
        // just /api/...), without needing to know the deploy path here.
        $uri = preg_replace('#^.*?/api(?=/|$)#', '', $uri) ?: '/';
        $this->path = rtrim($uri, '/') ?: '/';
        $this->query = $_GET;
    }

    public function json(): array
    {
        if (!isset($this->jsonBody)) {
            $raw = file_get_contents('php://input');
            $this->jsonBody = $raw ? (json_decode($raw, true) ?? []) : [];
        }
        return $this->jsonBody;
    }
}
