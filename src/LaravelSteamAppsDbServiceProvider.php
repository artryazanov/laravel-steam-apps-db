<?php

namespace Artryazanov\LaravelSteamAppsDb;

use Artryazanov\LaravelSteamAppsDb\Console\FetchSteamAppDetailsCommand;
use Artryazanov\LaravelSteamAppsDb\Console\FetchSteamAppNewsCommand;
use Artryazanov\LaravelSteamAppsDb\Console\ImportSteamAppsCommand;
use Illuminate\Support\ServiceProvider;

class LaravelSteamAppsDbServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register any bindings or services here
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register commands if running in the console
        if ($this->app->runningInConsole()) {
            $this->commands([
                FetchSteamAppDetailsCommand::class,
                FetchSteamAppNewsCommand::class,
                ImportSteamAppsCommand::class,
            ]);
        }
    }
}
