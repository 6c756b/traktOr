<?php

namespace TraktOr\Support;

/** Curated language list with TMDB locale mapping. 'en' is always available (Trakt's original language). */
final class Languages
{
    private const LANGUAGES = [
        'en' => ['locale' => 'en-US', 'label' => 'English'],
        'de' => ['locale' => 'de-DE', 'label' => 'Deutsch'],
        'es' => ['locale' => 'es-ES', 'label' => 'Español'],
        'fr' => ['locale' => 'fr-FR', 'label' => 'Français'],
        'it' => ['locale' => 'it-IT', 'label' => 'Italiano'],
        'pt' => ['locale' => 'pt-PT', 'label' => 'Português'],
        'nl' => ['locale' => 'nl-NL', 'label' => 'Nederlands'],
        'pl' => ['locale' => 'pl-PL', 'label' => 'Polski'],
        'sv' => ['locale' => 'sv-SE', 'label' => 'Svenska'],
        'tr' => ['locale' => 'tr-TR', 'label' => 'Türkçe'],
        'ru' => ['locale' => 'ru-RU', 'label' => 'Русский'],
        'ja' => ['locale' => 'ja-JP', 'label' => '日本語'],
        'ko' => ['locale' => 'ko-KR', 'label' => '한국어'],
        'zh' => ['locale' => 'zh-CN', 'label' => '中文'],
    ];

    public static function locale(string $code): string
    {
        return self::LANGUAGES[$code]['locale'] ?? 'en-US';
    }

    public static function isSupported(string $code): bool
    {
        return isset(self::LANGUAGES[$code]);
    }

    /** @return array<int, array{code: string, label: string}> */
    public static function all(): array
    {
        $result = [];
        foreach (self::LANGUAGES as $code => $info) {
            $result[] = ['code' => $code, 'label' => $info['label']];
        }
        return $result;
    }
}
