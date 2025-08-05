<?php

namespace Artryazanov\LaravelSteamAppsDb\Components;

use Artryazanov\LaravelSteamAppsDb\Console\NullCommand;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppCategory;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDetail;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDeveloper;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppGenre;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppMovie;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPriceInfo;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPublisher;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppRequirement;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppScreenshot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchSteamAppDetailsComponent
{
    /**
     * The Steam API endpoint for getting app details.
     *
     * @var string
     */
    private const STEAM_API_URL = 'https://store.steampowered.com/api/appdetails';

    /**
     * Fetch detailed information about Steam games and store it in the database.
     *
     * @param int $limit Number of apps to process
     * @param string|null $appid Optional Steam application ID to fetch details for a specific app
     * @param Command|null $command The command instance for output
     * @return void
     */
    public function fetchSteamAppDetails(int $limit, ?string $appid = null, ?Command $command = null): void
    {
        if (empty($command)) {
            $command = new NullCommand();
        }

        if ($appid) {
            $command->info("Starting to fetch Steam game details for specific appid: {$appid}...");

            // Get the specific app by appid
            $app = SteamApp::where('appid', $appid)->first();

            if (!$app) {
                $command->error("No Steam app found with appid: {$appid}");
                return;
            }

            $appsToProcess = collect([$app]);
            $totalApps = 1;
        } else {
            $command->info("Starting to fetch Steam game details (count: {$limit})...");

            // Get SteamApp records to process based on priority
            $appsToProcess = $this->getSteamAppsToProcess($limit);
            $totalApps = count($appsToProcess);

            if ($totalApps === 0) {
                $command->info('No Steam apps to process.');
                return;
            }
        }

        $command->info("Found {$totalApps} Steam apps to process.");

        $processed = 0;
        $success = 0;
        $failed = 0;

        foreach ($appsToProcess as $index => $app) {
            $currentIndex = $index + 1;
            $command->line('');
            $command->info("Processing app {$currentIndex} of {$totalApps}: {$app->name} (appid: {$app->appid})");

            try {
                // Fetch details from Steam API
                $details = $this->fetchAppDetailsFromApi($app->appid, $command);

                // Update the last_details_update field in the SteamApp model
                $app->update(['last_details_update' => Carbon::now()]);

                if (!$details) {
                    $command->warn("No details found for app {$app->name} (appid: {$app->appid})");
                    $failed++;
                    $command->info("Waiting 2 seconds before the next request...");
                    sleep(2);
                    continue;
                }

                // Store details in a database
                DB::beginTransaction();
                try {
                    $this->storeSteamAppDetails($app, $details);
                    DB::commit();
                    $success++;
                    $command->info("Successfully stored details for app {$app->name} (appid: {$app->appid})");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $command->error("Failed to store details for app {$app->name} (appid: {$app->appid}): {$e->getMessage()}");
                    Log::error("Failed to store Steam app details: {$e->getMessage()}", [
                        'exception' => $e,
                        'appid' => $app->appid,
                    ]);
                    $failed++;
                }
            } catch (\Exception $e) {
                $command->error("Error processing app {$app->name} (appid: {$app->appid}): {$e->getMessage()}");
                Log::error("Error processing Steam app: {$e->getMessage()}", [
                    'exception' => $e,
                    'appid' => $app->appid,
                ]);
                $failed++;
            }

            $processed++;

            // Add a 2-second delay between requests to avoid hitting rate limits
            if ($processed < $totalApps) {
                $command->info("Waiting 2 seconds before the next request...");
                sleep(2);
            }
        }

        $command->info("Fetch completed: {$success} apps processed successfully, {$failed} apps failed");
    }

    /**
     * Get SteamApp records to process
     *
     * @param int $limit
     * @return Collection
     */
    private function getSteamAppsToProcess(int $limit): Collection
    {
        $oneYearAgo = Carbon::now()->subYear();

        $appsWithoutDetails = SteamApp::query()
            ->whereNull('last_details_update')
            ->take($limit)
            ->get();

        if ($appsWithoutDetails->count() >= $limit) {
            return $appsWithoutDetails;
        }

        $remainingLimit = $limit - $appsWithoutDetails->count();

        $appsWithOldDetails = SteamApp::query()
            ->whereNotNull('last_details_update')
            ->where('last_details_update', '<', $oneYearAgo)
            ->take($remainingLimit)
            ->get();

        return $appsWithoutDetails->concat($appsWithOldDetails->all());
    }

    /**
     * Fetch details for a Steam app from the Steam API.
     *
     * @param int $appid
     * @param Command $command The command instance for output
     * @return array|null
     */
    private function fetchAppDetailsFromApi(int $appid, Command $command): ?array
    {
        $response = Http::get(self::STEAM_API_URL, [
            'appids' => $appid,
            'cc' => 'us',
            'l' => 'en',
        ]);

        if (!$response->successful()) {
            $command->error("Failed to fetch details for appid {$appid}: " . $response->status());
            return null;
        }

        $data = $response->json();

        if (!isset($data[$appid]['success']) || !$data[$appid]['success']) {
            $command->warn("No success response for appid {$appid}");
            return null;
        }

        if (!isset($data[$appid]['data'])) {
            $command->warn("No data found for appid {$appid}");
            return null;
        }

        return $data[$appid]['data'];
    }

    /**
     * Store Steam app details in the database.
     *
     * @param SteamApp $app
     * @param array $details
     * @return void
     */
    private function storeSteamAppDetails(SteamApp $app, array $details): void
    {
        // Create or update the SteamAppDetail record
        SteamAppDetail::updateOrCreate(
            ['steam_app_id' => $app->id],
            [
                'type' => $details['type'] ?? null,
                'name' => $details['name'],
                'required_age' => $details['required_age'] ?? 0,
                'is_free' => $details['is_free'] ?? false,
                'detailed_description' => $details['detailed_description'] ?? null,
                'about_the_game' => $details['about_the_game'] ?? null,
                'short_description' => $details['short_description'] ?? null,
                'supported_languages' => $details['supported_languages'] ?? null,
                'header_image' => $details['header_image'] ?? null,
                'capsule_image' => $details['capsule_image'] ?? null,
                'capsule_imagev5' => $details['capsule_imagev5'] ?? null,
                'website' => $details['website'] ?? null,
                'legal_notice' => $details['legal_notice'] ?? null,
                'windows' => $details['platforms']['windows'] ?? false,
                'mac' => $details['platforms']['mac'] ?? false,
                'linux' => $details['platforms']['linux'] ?? false,
                'background' => $details['background'] ?? null,
                'background_raw' => $details['background_raw'] ?? null,
                'release_date' => isset($details['release_date']['date']) ? Carbon::parse($details['release_date']['date']) : null,
                'coming_soon' => $details['release_date']['coming_soon'] ?? false,
                'support_url' => $details['support_info']['url'] ?? null,
                'support_email' => $details['support_info']['email'] ?? null,
            ]
        );

        // Store PC requirements
        if (isset($details['pc_requirements'])) {
            $this->storeRequirements($app, 'pc', $details['pc_requirements']);
        }

        // Store Mac requirements
        if (isset($details['mac_requirements'])) {
            $this->storeRequirements($app, 'mac', $details['mac_requirements']);
        }

        // Store Linux requirements
        if (isset($details['linux_requirements'])) {
            $this->storeRequirements($app, 'linux', $details['linux_requirements']);
        }

        // Store screenshots
        if (isset($details['screenshots']) && is_array($details['screenshots'])) {
            $this->storeScreenshots($app, $details['screenshots']);
        }

        // Store movies
        if (isset($details['movies']) && is_array($details['movies'])) {
            $this->storeMovies($app, $details['movies']);
        }

        // Store categories
        if (isset($details['categories']) && is_array($details['categories'])) {
            $this->storeCategories($app, $details['categories']);
        }

        // Store genres
        if (isset($details['genres']) && is_array($details['genres'])) {
            $this->storeGenres($app, $details['genres']);
        }

        // Store developers
        if (isset($details['developers']) && is_array($details['developers'])) {
            $this->storeDevelopers($app, $details['developers']);
        }

        // Store publishers
        if (isset($details['publishers']) && is_array($details['publishers'])) {
            $this->storePublishers($app, $details['publishers']);
        }

        // Store price info
        if (isset($details['price_overview'])) {
            $this->storePriceInfo($app, $details['price_overview']);
        }
    }

    /**
     * Store requirements for a platform.
     *
     * @param SteamApp $app
     * @param string $platform
     * @param array $requirements
     * @return void
     */
    private function storeRequirements(SteamApp $app, string $platform, array $requirements): void
    {
        SteamAppRequirement::updateOrCreate(
            [
                'steam_app_id' => $app->id,
                'platform' => $platform,
            ],
            [
                'minimum' => $requirements['minimum'] ?? null,
                'recommended' => $requirements['recommended'] ?? null,
            ]
        );
    }

    /**
     * Store screenshots.
     *
     * @param SteamApp $app
     * @param array $screenshots
     * @return void
     */
    private function storeScreenshots(SteamApp $app, array $screenshots): void
    {
        // Collect screenshot IDs from the incoming data
        $newScreenshotIds = collect($screenshots)->pluck('id')->toArray();

        // Get existing screenshots for this app
        $existingScreenshots = SteamAppScreenshot::where('steam_app_id', $app->id)->get();

        // Soft delete screenshots that are not in the new data
        $existingScreenshots
            ->whereNotIn('screenshot_id', $newScreenshotIds)
            ->each(function ($screenshot) {
                $screenshot->delete();
            });

        // Create or update screenshots
        foreach ($screenshots as $screenshotData) {
            SteamAppScreenshot::updateOrCreate(
                [
                    'steam_app_id' => $app->id,
                    'screenshot_id' => $screenshotData['id'],
                ],
                [
                    'path_thumbnail' => $screenshotData['path_thumbnail'] ?? null,
                    'path_full' => $screenshotData['path_full'] ?? null,
                ]
            );
        }
    }

    /**
     * Store movies.
     *
     * @param SteamApp $app
     * @param array $movies
     * @return void
     */
    private function storeMovies(SteamApp $app, array $movies): void
    {
        // Collect movie IDs from the incoming data
        $newMovieIds = collect($movies)->pluck('id')->toArray();

        // Get existing movies for this app
        $existingMovies = SteamAppMovie::where('steam_app_id', $app->id)->get();

        // Soft delete movies that are not in the new data
        $existingMovies
            ->whereNotIn('movie_id', $newMovieIds)
            ->each(function ($movie) {
                $movie->delete();
            });

        // Create or update movies
        foreach ($movies as $movieData) {
            SteamAppMovie::updateOrCreate(
                [
                    'steam_app_id' => $app->id,
                    'movie_id' => $movieData['id'],
                ],
                [
                    'name' => $movieData['name'] ?? null,
                    'thumbnail' => $movieData['thumbnail'] ?? null,
                    'webm_480' => $movieData['webm']['480'] ?? null,
                    'webm_max' => $movieData['webm']['max'] ?? null,
                    'mp4_480' => $movieData['mp4']['480'] ?? null,
                    'mp4_max' => $movieData['mp4']['max'] ?? null,
                    'highlight' => $movieData['highlight'] ?? false,
                ]
            );
        }
    }

    /**
     * Store categories.
     *
     * @param SteamApp $app
     * @param array $categories
     * @return void
     */
    private function storeCategories(SteamApp $app, array $categories): void
    {
        // Array to store category IDs for syncing
        $categoryIds = [];

        // Find or create categories
        foreach ($categories as $category) {
            $steamAppCategory = SteamAppCategory::query()->firstOrCreate(
                ['category_id' => $category['id']],
                ['description' => $category['description']]
            );

            $categoryIds[] = $steamAppCategory->id;
        }

        // Sync the relationship between the app and the categories
        $app->categories()->sync($categoryIds);
    }

    /**
     * Store genres.
     *
     * @param SteamApp $app
     * @param array $genres
     * @return void
     */
    private function storeGenres(SteamApp $app, array $genres): void
    {
        // Array to store genre IDs for syncing
        $genreIds = [];

        // Find or create genres
        foreach ($genres as $genre) {
            $steamAppGenre = SteamAppGenre::firstOrCreate(
                ['genre_id' => $genre['id']],
                ['description' => $genre['description']]
            );

            $genreIds[] = $steamAppGenre->id;
        }

        // Sync the relationship between the app and the genres
        $app->genres()->sync($genreIds);
    }

    /**
     * Store developers.
     *
     * @param SteamApp $app
     * @param array $developers
     * @return void
     */
    private function storeDevelopers(SteamApp $app, array $developers): void
    {
        // Array to store developer IDs for syncing
        $developerIds = [];

        // Find or create developers
        foreach ($developers as $developer) {
            $steamAppDeveloper = SteamAppDeveloper::firstOrCreate(
                ['name' => $developer]
            );

            $developerIds[] = $steamAppDeveloper->id;
        }

        // Sync the relationship between the app and the developers
        $app->developers()->sync($developerIds);
    }

    /**
     * Store publishers.
     *
     * @param SteamApp $app
     * @param array $publishers
     * @return void
     */
    private function storePublishers(SteamApp $app, array $publishers): void
    {
        // Array to store publisher IDs for syncing
        $publisherIds = [];

        // Find or create publishers
        foreach ($publishers as $publisher) {
            $steamAppPublisher = SteamAppPublisher::firstOrCreate(
                ['name' => $publisher]
            );

            $publisherIds[] = $steamAppPublisher->id;
        }

        // Sync the relationship between the app and the publishers
        $app->publishers()->sync($publisherIds);
    }

    /**
     * Store price info.
     *
     * @param SteamApp $app
     * @param array $priceInfo
     * @return void
     */
    private function storePriceInfo(SteamApp $app, array $priceInfo): void
    {
        SteamAppPriceInfo::updateOrCreate(
            ['steam_app_id' => $app->id],
            [
                'currency' => $priceInfo['currency'] ?? null,
                'initial' => $priceInfo['initial'] ?? null,
                'final' => $priceInfo['final'] ?? null,
                'discount_percent' => $priceInfo['discount_percent'] ?? 0,
                'initial_formatted' => $priceInfo['initial_formatted'] ?? null,
                'final_formatted' => $priceInfo['final_formatted'] ?? null,
            ]
        );
    }
}
