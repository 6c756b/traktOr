<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// CLI-only dispatcher for the single cron slot many shared hosting plans offer: the host's
// cron is free to fire more often (e.g. hourly), each job decides for itself in runIfDue()
// whether there's actually something to do right now. Just add further jobs to the list below.
$jobs = [
    new \TraktOr\Cron\NightlySyncJob(),
];

foreach ($jobs as $job) {
    try {
        $job->runIfDue();
    } catch (\Throwable $e) {
        error_log(get_class($job) . ': ' . $e->getMessage());
        echo get_class($job) . ": Fehler - " . $e->getMessage() . "\n";
    }
}
