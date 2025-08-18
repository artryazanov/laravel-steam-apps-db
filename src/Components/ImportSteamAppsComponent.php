<?php

namespace Artryazanov\LaravelSteamAppsDb\Components;

use Artryazanov\LaravelSteamAppsDb\Console\NullCommand;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;

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
}
