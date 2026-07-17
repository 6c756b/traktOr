<?php

namespace TraktOr\Support;

/** Single source of truth for the app version -- the VERSION file at the repo root
 *  (three levels up from here, both in the local repo and in a deployed api/ folder,
 *  see deploy.sh). Deliberately not in config.php, which deploy.sh never overwrites
 *  after the first manual upload and would therefore go stale. */
final class Version
{
    public static function current(): string
    {
        $file = __DIR__ . '/../../../VERSION';
        return is_file($file) ? trim(file_get_contents($file)) : 'dev';
    }
}
