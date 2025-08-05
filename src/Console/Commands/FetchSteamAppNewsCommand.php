<?php

namespace Artryazanov\LaravelSteamAppsDb\Console\Commands;

use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchSteamAppNewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:fetch-app-news {count=10 : Number of apps to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch latest news for Steam apps and store them in the database';

    /**
     * The Steam API endpoint for getting app news.
     *
     * @var string
     */
    private const STEAM_API_URL = 'https://api.steampowered.com/ISteamNews/GetNewsForApp/v0002/';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $argCount = $this->argument('count');
        if (!is_numeric($argCount)) {
            $this->error("Count has wrong value: $argCount!");
            return;
        }

        $limit = (int) $argCount;
        $this->info("Starting to fetch Steam app news (count: {$limit})...");

        // Get SteamApp records to process based on priority
        $appsToProcess = $this->getSteamAppsToProcess($limit);
        $totalApps = count($appsToProcess);

        if ($totalApps === 0) {
            $this->info('No Steam apps to process.');
            return;
        }

        $this->info("Found {$totalApps} Steam apps to process.");

        $processed = 0;
        $success = 0;
        $failed = 0;

        foreach ($appsToProcess as $index => $app) {
            $currentIndex = $index + 1;
            $this->line('');
            $this->info("Processing app {$currentIndex} of {$totalApps}: {$app->name} (appid: {$app->appid})");

            try {
                // Fetch news from Steam API
                $news = $this->fetchSteamAppNews($app->appid);

                // Update the last_news_update field in the SteamApp model
                $app->update(['last_news_update' => Carbon::now()]);

                if (!$news || empty($news['newsitems'])) {
                    $this->warn("No news found for app {$app->name} (appid: {$app->appid})");
                    $failed++;
                    $this->info("Waiting 2 seconds before the next request...");
                    sleep(2);
                    continue;
                }

                // Store news in the database
                DB::beginTransaction();
                try {
                    $this->storeSteamAppNews($app, $news['newsitems']);
                    DB::commit();
                    $success++;
                    $this->info("Successfully stored news for app {$app->name} (appid: {$app->appid})");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Failed to store news for app {$app->name} (appid: {$app->appid}): {$e->getMessage()}");
                    Log::error("Failed to store Steam app news: {$e->getMessage()}", [
                        'exception' => $e,
                        'appid' => $app->appid,
                    ]);
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing app {$app->name} (appid: {$app->appid}): {$e->getMessage()}");
                Log::error("Error processing Steam app news: {$e->getMessage()}", [
                    'exception' => $e,
                    'appid' => $app->appid,
                ]);
                $failed++;
            }

            $processed++;

            // Add a 2-second delay between requests to avoid hitting rate limits
            if ($processed < $totalApps) {
                $this->info("Waiting 2 seconds before the next request...");
                sleep(2);
            }
        }

        $this->info("Fetch completed: {$success} apps processed successfully, {$failed} apps failed");
    }

    /**
     * Get SteamApp records to process based on priority.
     *
     * Priority:
     * 1. Records with associated game and no loaded news
     * 2. Records without associated game and no loaded news
     * 3. Records with associated game and loaded news that haven't been updated for over a month
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getSteamAppsToProcess(int $limit): \Illuminate\Database\Eloquent\Collection
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
     * @return array|null
     */
    private function fetchSteamAppNews(int $appid): ?array
    {
        $response = Http::get(self::STEAM_API_URL, [
            'appid' => $appid,
            'count' => 100,
            'maxlength' => 0,
            'format' => 'json',
        ]);

        if (!$response->successful()) {
            $this->error("Failed to fetch news for appid {$appid}: " . $response->status());
            return null;
        }

        $data = $response->json();

        if (!isset($data['appnews']) || !isset($data['appnews']['newsitems'])) {
            $this->warn("No news data found for appid {$appid}");
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
