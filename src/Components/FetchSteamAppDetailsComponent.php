<?php

namespace Artryazanov\LaravelSteamAppsDb\Components;

use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppAchievementHighlighted;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppCategory;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppContentDescriptorId;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDemo;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDetail;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDeveloper;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDlc;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppGenre;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppMovie;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPackage;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPackageGroup;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPackageGroupSub;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPriceInfo;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPublisher;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppRating;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppRequirement;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppScreenshot;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
     * @param  string  $appid  Steam application ID to fetch details for a specific app
     *
     * @throws LaravelSteamAppsDbException
     */
    public function fetchSteamAppDetails(string $appid): void
    {
        // Get the specific app by appid
        $app = SteamApp::where('appid', $appid)->first();

        if (! $app) {
            return;
        }

        try {
            // Fetch details from Steam API
            $details = $this->fetchAppDetailsFromApi($app->appid);

            // Update the last_details_update field in the SteamApp model
            $app->update(['last_details_update' => Carbon::now()]);

            // Resolve library image URL and inject into details
            $details['library_image'] = $this->resolveLibraryImageUrl($app->appid);
            // Resolve library hero image URL and inject into details
            $details['library_hero_image'] = $this->resolveLibraryHeroImageUrl($app->appid);

            // Store details in a database
            DB::beginTransaction();
            try {
                $this->storeSteamAppDetails($app, $details);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $errorMessage = "Failed to store details for app {$app->name} (appid: {$app->appid}): {$e->getMessage()}";
                throw new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e);
            }
        } catch (Exception $e) {
            $errorMessage = "Error processing app {$app->name} (appid: {$app->appid}): {$e->getMessage()}";
            throw new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Fetch details for a Steam app from the Steam API.
     *
     * @throws Exception
     */
    private function fetchAppDetailsFromApi(int $appid): ?array
    {
        $response = Http::get(self::STEAM_API_URL, [
            'appids' => $appid,
            'cc' => 'us',
            'l' => 'en',
        ]);

        if (! $response->successful()) {
            throw new Exception("FetchAppDetailsFromApi, Steam API response status: {$response->status()}");
        }

        $data = $response->json();

        if (! isset($data[$appid]['success']) || ! $data[$appid]['success'] || ! isset($data[$appid]['data'])) {
            throw new Exception("FetchAppDetailsFromApi, Steam API response body: {$response->body()}");
        }

        return $data[$appid]['data'];
    }

    /**
     * Perform a lightweight existence check for a remote image URL (HEAD, fallback to GET).
     */
    private function resolveRemoteImageUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->connectTimeout(3)->send('HEAD', $url);
            if ($response->ok()) {
                return $url;
            }

            // Some servers may not allow HEAD; try GET as a fallback
            if ($response->status() === 405) {
                $getResponse = Http::timeout(5)->connectTimeout(3)->get($url);
                if ($getResponse->ok()) {
                    return $url;
                }
            }
        } catch (Exception $e) {
            // Ignore network errors and return null
        }

        return null;
    }

    /**
     * Check if the library image exists for given appid and return its URL or null.
     */
    private function resolveLibraryImageUrl(int $appid): ?string
    {
        $url = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$appid}/library_600x900.jpg";

        return $this->resolveRemoteImageUrl($url);
    }

    /**
     * Check if the library hero image exists for given appid and return its URL or null.
     */
    private function resolveLibraryHeroImageUrl(int $appid): ?string
    {
        $url = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$appid}/library_hero.jpg";

        return $this->resolveRemoteImageUrl($url);
    }

    /**
     * Store Steam app details in the database.
     */
    private function storeSteamAppDetails(SteamApp $app, array $details): void
    {
        // Prepare release date safely (avoid parsing non-date strings like 'Coming soon')
        $releaseDate = null;
        if (isset($details['release_date']) && is_array($details['release_date'])) {
            $comingSoonFlag = $details['release_date']['coming_soon'] ?? false;
            $dateString = $details['release_date']['date'] ?? null;

            if (! $comingSoonFlag && is_string($dateString)) {
                $trimmed = trim($dateString);
                // Skip common non-date placeholders
                $placeholders = ['coming soon', 'tba', 'to be announced', 'to be determined'];
                if ($trimmed !== '' && ! in_array(strtolower($trimmed), $placeholders, true)) {
                    try {
                        $releaseDate = Carbon::parse($trimmed);
                    } catch (Exception $e) {
                        // Leave as null if parsing fails
                        $releaseDate = null;
                    }
                }
            }
        }

        // Create or update the SteamAppDetail record
        SteamAppDetail::updateOrCreate(
            ['steam_app_id' => $app->id],
            [
                'type' => $details['type'] ?? null,
                'name' => $details['name'],
                'required_age' => $details['required_age'] ?? 0,
                'is_free' => $details['is_free'] ?? false,
                'controller_support' => $details['controller_support'] ?? null,
                'detailed_description' => $details['detailed_description'] ?? null,
                'about_the_game' => $details['about_the_game'] ?? null,
                'short_description' => $details['short_description'] ?? null,
                'supported_languages' => $details['supported_languages'] ?? null,
                'header_image' => $details['header_image'] ?? null,
                'library_image' => $details['library_image'] ?? null,
                'library_hero_image' => $details['library_hero_image'] ?? null,
                'capsule_image' => $details['capsule_image'] ?? null,
                'capsule_imagev5' => $details['capsule_imagev5'] ?? null,
                'website' => $details['website'] ?? null,
                'legal_notice' => $details['legal_notice'] ?? null,
                'drm_notice' => $details['drm_notice'] ?? null,
                'metacritic_score' => $details['metacritic']['score'] ?? null,
                'metacritic_url' => $details['metacritic']['url'] ?? null,
                'recommendations_total' => $details['recommendations']['total'] ?? null,
                'achievements_total' => $details['achievements']['total'] ?? null,
                'windows' => $details['platforms']['windows'] ?? false,
                'mac' => $details['platforms']['mac'] ?? false,
                'linux' => $details['platforms']['linux'] ?? false,
                'background' => $details['background'] ?? null,
                'background_raw' => $details['background_raw'] ?? null,
                'content_descriptors_notes' => $details['content_descriptors']['notes'] ?? null,
                'release_date' => $releaseDate,
                'coming_soon' => $details['release_date']['coming_soon'] ?? false,
                'support_url' => $details['support_info']['url'] ?? null,
                'support_email' => $details['support_info']['email'] ?? null,
            ]
        );

        // Store DLC list
        if (isset($details['dlc']) && is_array($details['dlc'])) {
            $this->storeDlcs($app, $details['dlc']);
        }

        // Store Demos
        if (isset($details['demos']) && is_array($details['demos'])) {
            $this->storeDemos($app, $details['demos']);
        }

        // Store Packages
        if (isset($details['packages']) && is_array($details['packages'])) {
            $this->storePackages($app, $details['packages']);
        }

        // Store Package Groups
        if (isset($details['package_groups']) && is_array($details['package_groups'])) {
            $this->storePackageGroups($app, $details['package_groups']);
        }

        // Store highlighted achievements
        if (isset($details['achievements']['highlighted']) && is_array($details['achievements']['highlighted'])) {
            $this->storeAchievementsHighlighted($app, $details['achievements']['highlighted']);
        }

        // Store content descriptor IDs
        if (isset($details['content_descriptors']['ids']) && is_array($details['content_descriptors']['ids'])) {
            $this->storeContentDescriptorIds($app, $details['content_descriptors']['ids']);
        }

        // Store ratings
        if (isset($details['ratings']) && is_array($details['ratings'])) {
            $this->storeRatings($app, $details['ratings']);
        }

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
     * Store DLCs list.
     */
    private function storeDlcs(SteamApp $app, array $dlcIds): void
    {
        $dlcIds = array_values(array_unique(array_filter($dlcIds, fn ($v) => is_numeric($v))));
        $existing = SteamAppDlc::where('steam_app_id', $app->id)->get();
        $existingIds = $existing->pluck('dlc_appid')->toArray();

        // Delete missing
        SteamAppDlc::where('steam_app_id', $app->id)
            ->whereNotIn('dlc_appid', $dlcIds)
            ->delete();

        // Upsert
        foreach ($dlcIds as $dlcId) {
            SteamAppDlc::updateOrCreate(
                ['steam_app_id' => $app->id, 'dlc_appid' => (int) $dlcId],
                []
            );
        }
    }

    /**
     * Store demos list.
     */
    private function storeDemos(SteamApp $app, array $demos): void
    {
        $ids = collect($demos)->pluck('appid')->filter()->unique()->values()->all();
        SteamAppDemo::where('steam_app_id', $app->id)
            ->whereNotIn('appid', $ids)
            ->delete();

        foreach ($demos as $demo) {
            if (! isset($demo['appid'])) {
                continue;
            }
            SteamAppDemo::updateOrCreate(
                ['steam_app_id' => $app->id, 'appid' => (int) $demo['appid']],
                ['description' => $demo['description'] ?? null]
            );
        }
    }

    /**
     * Store packages list.
     */
    private function storePackages(SteamApp $app, array $packageIds): void
    {
        $packageIds = array_values(array_unique(array_filter($packageIds, fn ($v) => is_numeric($v))));
        SteamAppPackage::where('steam_app_id', $app->id)
            ->whereNotIn('package_id', $packageIds)
            ->delete();

        foreach ($packageIds as $pid) {
            SteamAppPackage::updateOrCreate(
                ['steam_app_id' => $app->id, 'package_id' => (int) $pid],
                []
            );
        }
    }

    /**
     * Store package groups and subs.
     */
    private function storePackageGroups(SteamApp $app, array $groups): void
    {
        $names = collect($groups)->pluck('name')->filter()->unique()->values()->all();
        // Delete removed groups (cascade removes subs)
        SteamAppPackageGroup::where('steam_app_id', $app->id)
            ->whereNotIn('name', $names)
            ->get()
            ->each(function ($group) {
                $group->delete();
            });

        foreach ($groups as $group) {
            if (! isset($group['name'])) {
                continue;
            }
            $groupModel = SteamAppPackageGroup::updateOrCreate(
                ['steam_app_id' => $app->id, 'name' => $group['name']],
                [
                    'title' => $group['title'] ?? null,
                    'description' => $group['description'] ?? null,
                    'selection_text' => $group['selection_text'] ?? null,
                    'save_text' => $group['save_text'] ?? null,
                    'display_type' => $group['display_type'] ?? 0,
                    'is_recurring_subscription' => $group['is_recurring_subscription'] ?? null,
                ]
            );

            // Subs
            $subs = $group['subs'] ?? [];
            $packageids = collect($subs)->pluck('packageid')->filter()->unique()->values()->all();
            SteamAppPackageGroupSub::where('steam_app_package_group_id', $groupModel->id)
                ->whereNotIn('packageid', $packageids)
                ->delete();
            foreach ($subs as $sub) {
                if (! isset($sub['packageid'])) {
                    continue;
                }
                SteamAppPackageGroupSub::updateOrCreate(
                    [
                        'steam_app_package_group_id' => $groupModel->id,
                        'packageid' => (int) $sub['packageid'],
                    ],
                    [
                        'percent_savings_text' => $sub['percent_savings_text'] ?? null,
                        'percent_savings' => $sub['percent_savings'] ?? 0,
                        'option_text' => $sub['option_text'] ?? null,
                        'option_description' => $sub['option_description'] ?? null,
                        'can_get_free_license' => $sub['can_get_free_license'] ?? null,
                        'is_free_license' => (bool) ($sub['is_free_license'] ?? false),
                        'price_in_cents_with_discount' => $sub['price_in_cents_with_discount'] ?? null,
                    ]
                );
            }
        }
    }

    /**
     * Store highlighted achievements.
     */
    private function storeAchievementsHighlighted(SteamApp $app, array $highlighted): void
    {
        $keys = collect($highlighted)->map(fn ($h) => ($h['name'] ?? '').'|'.($h['path'] ?? ''))->all();
        // Delete not present
        $existing = SteamAppAchievementHighlighted::where('steam_app_id', $app->id)->get();
        foreach ($existing as $row) {
            $key = $row->name.'|'.($row->path ?? '');
            if (! in_array($key, $keys, true)) {
                $row->delete();
            }
        }

        foreach ($highlighted as $h) {
            $name = $h['name'] ?? null;
            $path = $h['path'] ?? null;
            if (! $name) {
                continue;
            }
            SteamAppAchievementHighlighted::updateOrCreate(
                ['steam_app_id' => $app->id, 'name' => $name, 'path' => $path],
                []
            );
        }
    }

    /**
     * Store content descriptor IDs.
     */
    private function storeContentDescriptorIds(SteamApp $app, array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($v) => is_numeric($v))));
        SteamAppContentDescriptorId::where('steam_app_id', $app->id)
            ->whereNotIn('descriptor_id', $ids)
            ->delete();
        foreach ($ids as $id) {
            SteamAppContentDescriptorId::updateOrCreate(
                ['steam_app_id' => $app->id, 'descriptor_id' => (int) $id],
                []
            );
        }
    }

    /**
     * Store ratings.
     */
    private function storeRatings(SteamApp $app, array $ratings): void
    {
        $boards = array_keys($ratings);
        SteamAppRating::where('steam_app_id', $app->id)
            ->whereNotIn('board', $boards)
            ->delete();

        foreach ($ratings as $board => $data) {
            SteamAppRating::updateOrCreate(
                ['steam_app_id' => $app->id, 'board' => (string) $board],
                [
                    'rating' => $data['rating'] ?? null,
                    'descriptors' => $data['descriptors'] ?? null,
                    'display_online_notice' => $data['display_online_notice'] ?? null,
                    'required_age' => $data['required_age'] ?? null,
                    'use_age_gate' => $data['use_age_gate'] ?? null,
                    'banned' => $data['banned'] ?? null,
                    'rating_generated' => $data['rating_generated'] ?? null,
                ]
            );
        }
    }

    /**
     * Store categories.
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
