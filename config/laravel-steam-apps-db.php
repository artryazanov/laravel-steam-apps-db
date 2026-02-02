<?php

return [
    // Toggle Steam news scanning (jobs dispatch). Default: disabled
    'enable_news_scanning' => env('LSADB_ENABLE_NEWS_SCANNING', false),

    // The decay (lock) duration for FetchSteamApp* jobs in seconds.
    'decay_seconds' => env('LSADB_DECAY_SECONDS', 1),

    // Queue name for dispatched jobs (e.g., high, default, low). Default: default
    'queue' => env('LSADB_QUEUE', 'default'),
];
