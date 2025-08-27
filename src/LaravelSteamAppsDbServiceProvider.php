<?php

namespace Artryazanov\LaravelSteamAppsDb;

use Artryazanov\LaravelSteamAppsDb\Console\ImportSteamAppsCommand;
use Illuminate\Support\ServiceProvider;

class LaravelSteamAppsDbServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-steam-apps-db.php', 'laravel-steam-apps-db');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register commands if running in the console
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportSteamAppsCommand::class,
            ]);

            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/laravel-steam-apps-db.php' => function_exists('config_path')
                    ? config_path('laravel-steam-apps-db.php')
                    : base_path('config/laravel-steam-apps-db.php'),
            ], 'laravel-steam-apps-db-config');
        }
    }
}
