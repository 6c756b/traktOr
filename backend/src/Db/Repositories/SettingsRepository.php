<?php

namespace TraktOr\Db\Repositories;

use TraktOr\Db\Database;

final class SettingsRepository
{
    public function getLanguage(): string
    {
        $stmt = Database::pdo()->query('SELECT language FROM app_settings WHERE id = 1');
        $language = $stmt->fetchColumn();
        return $language !== false ? $language : 'en';
    }

    public function setLanguage(string $language): void
    {
        Database::pdo()->prepare(
            'INSERT INTO app_settings (id, language) VALUES (1, :language)
             ON DUPLICATE KEY UPDATE language = VALUES(language)'
        )->execute(['language' => $language]);
    }

    /** null means "follow the OS setting" -- the user hasn't flipped the switch yet. */
    public function getTheme(): ?string
    {
        $stmt = Database::pdo()->query('SELECT theme FROM app_settings WHERE id = 1');
        $theme = $stmt->fetchColumn();
        return $theme !== false ? $theme : null;
    }

    public function setTheme(?string $theme): void
    {
        Database::pdo()->prepare(
            'INSERT INTO app_settings (id, theme) VALUES (1, :theme)
             ON DUPLICATE KEY UPDATE theme = VALUES(theme)'
        )->execute(['theme' => $theme]);
    }
}
