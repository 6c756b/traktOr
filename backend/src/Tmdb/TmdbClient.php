<?php

namespace TraktOr\Tmdb;

use TraktOr\Support\Config;

final class TmdbClient
{
    private const API_BASE = 'https://api.themoviedb.org/3';
    private const IMAGE_BASE = 'https://image.tmdb.org/t/p';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = Config::load()['tmdb']['api_key'] ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Fetches poster/backdrop + localized title/description for the given TMDB locale
     * (e.g. 'de-DE'). If TMDB has no translation for a title, title/overview are an
     * empty string -- the caller should then fall back to Trakt's English values.
     *
     * @param int[] $tmdbIds
     * @param 'tv'|'movie' $mediaType
     * @return array<int, array{poster_url: ?string, backdrop_url: ?string, title: string, overview: string}>
     */
    public function getManyDetails(array $tmdbIds, string $mediaType, string $locale, int $concurrency = 8): array
    {
        if (!$this->isConfigured() || $tmdbIds === []) {
            return [];
        }

        $results = [];
        foreach (array_chunk(array_unique($tmdbIds), $concurrency) as $batch) {
            $this->runBatch($batch, $mediaType, $locale, $results);
        }
        return $results;
    }

    /**
     * Fetches localized episode titles for multiple seasons in one go (one request per
     * season returns all episodes in it -- much more efficient than Trakt's translation
     * endpoint, which would have to be queried per episode individually).
     *
     * @param int[] $seasonNumbers
     * @return array<int, array<int, string>> [season => [episode number => title]]
     */
    public function getManySeasonEpisodeTitles(int $tmdbId, array $seasonNumbers, string $locale, int $concurrency = 8): array
    {
        if (!$this->isConfigured() || $seasonNumbers === []) {
            return [];
        }

        $results = [];
        foreach (array_chunk(array_unique($seasonNumbers), $concurrency) as $batch) {
            $this->runSeasonBatch($tmdbId, $batch, $locale, $results);
        }
        return $results;
    }

    private function runBatch(array $ids, string $mediaType, string $locale, array &$results): void
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($ids as $id) {
            $url = self::API_BASE . "/{$mediaType}/{$id}?api_key=" . urlencode($this->apiKey) . '&language=' . urlencode($locale);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => 'TraktOr/1.0',
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$id] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if (curl_multi_select($multi) === -1) {
                usleep(10_000);
            }
        } while ($running > 0);

        $titleField = $mediaType === 'tv' ? 'name' : 'title';

        foreach ($handles as $id => $ch) {
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($status >= 200 && $status < 300) {
                $data = json_decode(curl_multi_getcontent($ch), true) ?? [];
                $results[$id] = [
                    'poster_url' => isset($data['poster_path']) ? self::IMAGE_BASE . '/w500' . $data['poster_path'] : null,
                    'backdrop_url' => isset($data['backdrop_path']) ? self::IMAGE_BASE . '/w1280' . $data['backdrop_path'] : null,
                    'title' => trim($data[$titleField] ?? ''),
                    'overview' => trim($data['overview'] ?? ''),
                ];
            }
            curl_multi_remove_handle($multi, $ch);
        }
        curl_multi_close($multi);
    }

    private function runSeasonBatch(int $tmdbId, array $seasonNumbers, string $locale, array &$results): void
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($seasonNumbers as $season) {
            $url = self::API_BASE . "/tv/{$tmdbId}/season/{$season}?api_key=" . urlencode($this->apiKey) . '&language=' . urlencode($locale);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => 'TraktOr/1.0',
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$season] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if (curl_multi_select($multi) === -1) {
                usleep(10_000);
            }
        } while ($running > 0);

        foreach ($handles as $season => $ch) {
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($status >= 200 && $status < 300) {
                $data = json_decode(curl_multi_getcontent($ch), true) ?? [];
                $titles = [];
                foreach ($data['episodes'] ?? [] as $episode) {
                    $name = trim($episode['name'] ?? '');
                    if ($name !== '') {
                        $titles[$episode['episode_number']] = $name;
                    }
                }
                if ($titles !== []) {
                    $results[$season] = $titles;
                }
            }
            curl_multi_remove_handle($multi, $ch);
        }
        curl_multi_close($multi);
    }
}
