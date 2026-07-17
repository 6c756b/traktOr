<?php

// General cron router for a hosting account with multiple projects and only one
// cron slot. Deliberately lives OUTSIDE each individual project folder (e.g. directly in
// the account root directory, one folder above "traktor/"), so that the one cron slot can
// serve multiple projects at once without being permanently tied to just one of them.
//
// Each target is started as its OWN PHP process (via shell_exec, not via require) --
// this way each project's autoloader/bootstrap/namespaces stay cleanly separated from
// each other, even if two projects were built independently of one another.
//
// Setup:
// 1. Place this file OUTSIDE the traktor folder, e.g. directly in the account root:
//    /var/www/YOUR_ACCOUNT/cron.php
// 2. Add each project that has its own cron entry point to $targets below.
// 3. In the host's cron panel, register ONLY this one file:
//    php /var/www/YOUR_ACCOUNT/cron.php
//    (no longer point directly at an individual project's cron.php)

$targets = [
    __DIR__ . '/traktor/api/cron.php',
    // __DIR__ . '/other-project/cron.php',
];

foreach ($targets as $target) {
    if (!is_file($target)) {
        echo "Übersprungen (nicht gefunden): {$target}\n";
        continue;
    }

    echo "=== {$target} ===\n";
    $output = shell_exec('php ' . escapeshellarg($target) . ' 2>&1');
    echo $output !== null ? $output : "(shell_exec lieferte nichts zurück -- evtl. auf dem Hoster deaktiviert, siehe unten)\n";
    echo "\n";
}

// If shell_exec() is disabled by the host (some shared hosting plans block it
// for security reasons): replace the block above with individual require calls, provided
// it's ensured that the projects don't get in each other's way (e.g. identically named
// global functions/constants outside their own namespaces). TraktOr's own cron.php is
// unproblematic in this regard, other projects would need to verify this themselves if in doubt.
