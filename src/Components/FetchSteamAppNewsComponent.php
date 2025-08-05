<?php

namespace Artryazanov\LaravelSteamAppsDb\Components;

use Artryazanov\LaravelSteamAppsDb\Console\NullCommand;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchSteamAppNewsComponent
{
    /**
     * The Steam API endpoint for getting app news.
     *
     * @var string
     */
    private const STEAM_API_URL = 'https://api.steampowered.com/ISteamNews/GetNewsForApp/v0002/';

    /**
     * Fetch the latest news for Steam apps and store them in the database.
     *
     * @param int $limit Number of apps to process
     * @param int|null $appid Specific Steam app ID to fetch news for
     * @param Command|null $command The command instance for output
     * @return void
     */
    public function fetchSteamAppNews(int $limit, ?int $appid = null, ?Command $command = null): void
    {
        if (empty($command)) {
            $command = new NullCommand();
        }

        if ($appid) {
            $command->info("Starting to fetch news for specific Steam app (appid: {$appid})...");

            // Get the specific app by appid
            $app = SteamApp::where('appid', $appid)->first();

            if (!$app) {
                $command->error("Steam app with appid {$appid} not found in the database.");
                return;
            }

            $appsToProcess = collect([$app]);
            $totalApps = 1;

            $command->info("Found Steam app: {$app->name} (appid: {$app->appid})");
        } else {
            $command->info("Starting to fetch Steam app news (count: {$limit})...");

            // Get SteamApp records to process based on priority
            $appsToProcess = $this->getSteamAppsToProcess($limit);
            $totalApps = count($appsToProcess);

            if ($totalApps === 0) {
                $command->info('No Steam apps to process.');
                return;
            }

            $command->info("Found {$totalApps} Steam apps to process.");
        }

        $processed = 0;
        $success = 0;
        $failed = 0;

        foreach ($appsToProcess as $index => $app) {
            $currentIndex = $index + 1;
            $command->line('');
            $command->info("Processing app {$currentIndex} of {$totalApps}: {$app->name} (appid: {$app->appid})");

            try {
                // Fetch news from Steam API
                $news = $this->fetchNewsFromApi($app->appid, $command);

                // Update the last_news_update field in the SteamApp model
                $app->update(['last_news_update' => Carbon::now()]);

                if (!$news || empty($news['newsitems'])) {
                    $command->warn("No news found for app {$app->name} (appid: {$app->appid})");
                    $failed++;
                    $command->info("Waiting 2 seconds before the next request...");
                    sleep(2);
                    continue;
                }

                // Store news in the database
                DB::beginTransaction();
                try {
                    $this->storeSteamAppNews($app, $news['newsitems']);
                    DB::commit();
                    $success++;
                    $command->info("Successfully stored news for app {$app->name} (appid: {$app->appid})");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $command->error("Failed to store news for app {$app->name} (appid: {$app->appid}): {$e->getMessage()}");
                    Log::error("Failed to store Steam app news: {$e->getMessage()}", [
                        'exception' => $e,
                        'appid' => $app->appid,
                    ]);
                    $failed++;
                }
            } catch (\Exception $e) {
                $command->error("Error processing app {$app->name} (appid: {$app->appid}): {$e->getMessage()}");
                Log::error("Error processing Steam app news: {$e->getMessage()}", [
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
        $oneMonthAgo = Carbon::now()->subMonth();

        $appsWithoutNews = SteamApp::query()
            ->whereNull('last_news_update')
            ->take($limit)
            ->get();

        if ($appsWithoutNews->count() >= $limit) {
            return $appsWithoutNews;
        }

        $remainingLimit = $limit - $appsWithoutNews->count();

        $appsWithOldNews = SteamApp::query()
            ->whereNotNull('last_news_update')
            ->where('last_news_update', '<', $oneMonthAgo)
            ->take($remainingLimit)
            ->get();

        return $appsWithoutNews->concat($appsWithOldNews->all());
    }

    /**
     * Fetch news for a Steam app from the Steam API.
     *
     * @param int $appid
     * @param Command $command The command instance for output
     * @return array|null
     */
    private function fetchNewsFromApi(int $appid, Command $command): ?array
    {
        $response = Http::get(self::STEAM_API_URL, [
            'appid' => $appid,
            'count' => 100,
            'maxlength' => 0,
            'format' => 'json',
        ]);

        if (!$response->successful()) {
            $command->error("Failed to fetch news for appid {$appid}: " . $response->status());
            return null;
        }

        $data = $response->json();

        if (!isset($data['appnews']) || !isset($data['appnews']['newsitems'])) {
            $command->warn("No news data found for appid {$appid}");
            return null;
        }

        return $data['appnews'];
    }

    /**
     * Store Steam app news in the database.
     *
     * @param SteamApp $app
     * @param array $newsItems
     * @return void
     */
    private function storeSteamAppNews(SteamApp $app, array $newsItems): void
    {
        // Create or update news items
        foreach ($newsItems as $newsItem) {
            SteamAppNews::updateOrCreate(
                [
                    'gid' => $newsItem['gid'],
                ],
                [
                    'steam_app_id' => $app->id,
                    'title' => $newsItem['title'],
                    'url' => $newsItem['url'] ?? null,
                    'is_external_url' => $newsItem['is_external_url'] ?? false,
                    'author' => $newsItem['author'] ?? null,
                    'contents' => $newsItem['contents'] ?? null,
                    'feedlabel' => $newsItem['feedlabel'] ?? null,
                    'date' => $newsItem['date'] ?? null,
                    'feedname' => $newsItem['feedname'] ?? null,
                    'feed_type' => $newsItem['feed_type'] ?? 0,
                    'tags' => isset($newsItem['tags']) ? $newsItem['tags'] : null,
                ]
            );
        }
    }
}
