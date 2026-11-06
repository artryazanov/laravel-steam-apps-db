<?php

return [
    // Steam Web API key (used for Workshop IPublishedFileService endpoints)
    'api_key' => env('LSADB_STEAM_API_KEY', env('STEAM_API_KEY')),

    // Toggle Steam news scanning (jobs dispatch). Default: disabled
    'enable_news_scanning' => env('LSADB_ENABLE_NEWS_SCANNING', false),

    // Toggle Steam Workshop (UGC) scanning. Default: disabled
    'enable_workshop_scanning' => env('LSADB_ENABLE_WORKSHOP_SCANNING', false),

    // The decay (lock) duration for FetchSteamApp* jobs in seconds.
    'decay_seconds' => env('LSADB_DECAY_SECONDS', 1),

    // Queue name for dispatched jobs (e.g., high, default, low). Default: default
    'queue' => env('LSADB_QUEUE', 'default'),

    // Chunk size when importing Steam apps list. Default: 500
    'chunk_size' => env('LSADB_CHUNK_SIZE', 500),
];
