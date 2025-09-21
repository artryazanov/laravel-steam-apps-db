<?php

namespace Artryazanov\LaravelSteamAppsDb\Components;

use Artryazanov\LaravelSteamAppsDb\Console\NullCommand;
use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class ImportSteamAppsComponent
{
    /**
     * The Steam API endpoint for getting the app list.
     *
     * @var string
     */
    private const STEAM_API_URL = 'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json';

    /**
     * Import Steam apps from the Steam API and store them in the database.
     *
     * @param  Command|null  $command  The command instance for output
     */
    public function importSteamApps(?Command $command = null): void
    {
        if (empty($command)) {
            $command = new NullCommand;
        }

        try {
            $command->info('Fetching data from Steam API...');

            // Make the HTTP request to the Steam API
            $response = Http::get(self::STEAM_API_URL);

            if (! $response->successful()) {
                $command->error('Failed to fetch data from Steam API: '.$response->status());

                return;
            }

            $data = $response->json();

            if (! isset($data['applist']['apps']) || ! is_array($data['applist']['apps'])) {
                $command->error('Invalid response format from Steam API');

                return;
            }

            $apps = $data['applist']['apps'];
            $totalApps = count($apps);

            $command->info("Found {$totalApps} apps in the Steam API response");

            // Process the apps in chunks to avoid memory issues
            $chunkSize = 1000;
            $chunks = array_chunk($apps, $chunkSize);

            $command->info('Processing apps in chunks of '.$chunkSize);

            $processed = 0;
            $created = 0;
            $updated = 0;

            foreach ($chunks as $index => $chunk) {
                $command->info('Processing chunk '.($index + 1).' of '.count($chunks));

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

                    // Conditionally dispatch jobs to update details and (optionally) news for this app
                    try {
                        $shouldDispatch = $this->shouldDispatch($steamApp);
                        if ($shouldDispatch) {
                            $queue = (string) config('laravel-steam-apps-db.queue', 'default');
                            FetchSteamAppDetailsJob::dispatch((int) $steamApp->appid)->onQueue($queue);
                            // News scanning is optional and disabled by default via config
                            if ((bool) config('laravel-steam-apps-db.enable_news_scanning', false)) {
                                FetchSteamAppNewsJob::dispatch((int) $steamApp->appid)->onQueue($queue);
                            }
                        }
                    } catch (Exception $e) {
                        $command->error("Failed to dispatch jobs for appid {$steamApp->appid}: {$e->getMessage()}");
                    }

                    $processed++;
                }

                // Show progress
                $command->info("Processed {$processed} of {$totalApps} apps");
            }

            $command->info("Import completed: {$created} apps created, {$updated} apps updated");

        } catch (Exception $e) {
            $errorMessage = 'An error occurred during import: '.$e->getMessage();
            $command->error($errorMessage);
            report(new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e));
        }
    }

    /**
     * Determine whether update jobs should be dispatched for the given SteamApp.
     *
     * Rules:
     * - If last_details_update is null, dispatch.
     * - Else, choose the minimal interval based on release age thresholds and compare to last update.
     */
    private function shouldDispatch(SteamApp $steamApp, ?Carbon $now = null): bool
    {
        $lastUpdate = $steamApp->last_details_update; // Carbon|null
        if (empty($lastUpdate)) {
            return true;
        }

        $thresholds = config('laravel-steam-apps-db.release_age_thresholds', []);
        $intervals = config('laravel-steam-apps-db.details_update_intervals', []);

        $recentMonths = (int) ($thresholds['recent_months'] ?? 6);
        $midMaxYears = (int) ($thresholds['mid_max_years'] ?? 2);

        $recentDays = (int) ($intervals['recent_days'] ?? 7);
        $midDays = (int) ($intervals['mid_days'] ?? 30);
        $oldDays = (int) ($intervals['old_days'] ?? 183);

        $now ??= Carbon::now();

        $detail = $steamApp->detail; // may be null
        $releaseDate = $detail?->release_date; // Carbon|null

        // Classify release age
        $isRecent = false;
        $isMid = false;

        if (empty($releaseDate)) {
            $isRecent = true; // treat unknown release date as recent
        } else {
            if ($releaseDate->greaterThan($now)) {
                $isRecent = true; // future release treated as recent
            } else {
                $monthsSinceRelease = $releaseDate->diffInMonths($now);
                if ($monthsSinceRelease < $recentMonths) {
                    $isRecent = true;
                } else {
                    $yearsSinceRelease = $releaseDate->diffInYears($now);
                    if ($yearsSinceRelease < $midMaxYears) {
                        $isMid = true;
                    }
                }
            }
        }

        // Determine minimal days since last update required
        $minDaysSinceUpdate = $isRecent ? $recentDays : ($isMid ? $midDays : $oldDays);

        // Dispatch only if more than configured interval has passed since last update
        return $lastUpdate->diffInDays($now) > $minDaysSinceUpdate;
    }
}
