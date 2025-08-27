<?php

return [
    // The decay (lock) duration for FetchSteamApp* jobs in seconds.
    'decay_seconds' => env('LARAVEL_STEAM_APPS_DB_DECAY_SECONDS', 1),

    // Thresholds for classifying release age
    'release_age_thresholds' => [
        // Consider a game "recently released" if it was released within this many months
        'recent_months' => env('LSADB_RECENT_RELEASE_MONTHS', 6),
        // Consider a game "mid-age" if it was released more than recent_months ago and within this many years
        'mid_max_years' => env('LSADB_MID_RELEASE_MAX_YEARS', 2),
    ],

    // Intervals (in days) after which details should be refreshed depending on the release age
    'details_update_intervals' => [
        // For recent releases (<= recent_months) or when release_date is unknown
        'recent_days' => env('LSADB_RECENT_DETAILS_INTERVAL_DAYS', 7), // 1 week by default
        // For mid-age releases (> recent_months and <= mid_max_years)
        'mid_days' => env('LSADB_MID_DETAILS_INTERVAL_DAYS', 30), // ~1 month by default
        // For old releases (> mid_max_years)
        'old_days' => env('LSADB_OLD_DETAILS_INTERVAL_DAYS', 183), // ~6 months by default
    ],
];
