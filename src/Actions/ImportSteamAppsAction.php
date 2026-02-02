<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Actions;

use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Exception;
use Illuminate\Support\Facades\Log;

class ImportSteamAppsAction
{
    public function __construct(
        protected SteamApiClient $steamApiClient
    ) {}

    /**
     * Import Steam apps from the Steam API and store them in the database.
     *
     * @param  callable(string): void  $infoCallback
     * @param  callable(string): void  $errorCallback
     */
    public function execute(?callable $infoCallback = null, ?callable $errorCallback = null): void
    {
        $infoCallback ??= fn (string $msg) => Log::info($msg);
        $errorCallback ??= fn (string $msg) => Log::error($msg);

        try {
            $infoCallback('Fetching data from Steam API...');

            $apps = $this->steamApiClient->getAppList();
            $totalApps = count($apps);

            $infoCallback("Found {$totalApps} apps in the Steam API response");

            // Process the apps in chunks to avoid memory issues
            $chunkSize = 1000;
            $chunks = array_chunk($apps, $chunkSize);

            $infoCallback('Processing apps in chunks of '.$chunkSize);

            $processed = 0;
            $created = 0;
            $updated = 0;
            $queue = (string) config('laravel-steam-apps-db.queue', 'default');
            $enableNewsScanning = (bool) config('laravel-steam-apps-db.enable_news_scanning', false);

            foreach ($chunks as $index => $chunk) {
                $infoCallback('Processing chunk '.($index + 1).' of '.count($chunks));

                foreach ($chunk as $app) {
                    // Skip apps with empty names
                    if (empty($app['name'])) {
                        continue;
                    }

                    // Find or create the SteamApp record
                    $steamApp = SteamApp::updateOrCreate(
                        ['appid' => $app['appid']],
                        ['name' => $app['name']]
                    );

                    if ($steamApp->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    // Always dispatch jobs to update details and (optionally) news for this app
                    try {
                        FetchSteamAppDetailsJob::dispatch((int) $steamApp->appid)->onQueue($queue);

                        if ($enableNewsScanning) {
                            FetchSteamAppNewsJob::dispatch((int) $steamApp->appid)->onQueue($queue);
                        }
                    } catch (Exception $e) {
                        $errorCallback("Failed to dispatch jobs for appid {$steamApp->appid}: {$e->getMessage()}");
                    }

                    $processed++;
                }

                $infoCallback("Processed {$processed} of {$totalApps} apps");
            }

            $infoCallback("Import completed: {$created} apps created, {$updated} apps updated");

        } catch (LaravelSteamAppsDbException $e) {
            $errorCallback($e->getMessage());
            report($e);
        } catch (Exception $e) {
            $errorMessage = 'An error occurred during import: '.$e->getMessage();
            $errorCallback($errorMessage);
            report(new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e));
        }
    }
}
