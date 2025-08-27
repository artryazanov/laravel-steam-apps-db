<?php

return [
    // The decay (lock) duration for FetchSteamApp* jobs in seconds.
    'decay_seconds' => env('LARAVEL_STEAM_APPS_DB_DECAY_SECONDS', 2),
];
