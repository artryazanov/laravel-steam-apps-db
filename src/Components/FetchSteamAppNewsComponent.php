<?php

namespace Artryazanov\LaravelSteamAppsDb\Components;

use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
     * @param string $appid Specific Steam app ID to fetch news for
     * @throws LaravelSteamAppsDbException
     */
    public function fetchSteamAppNews(string $appid): void
    {
        // Get the specific app by appid
        $app = SteamApp::where('appid', $appid)->first();

        if (! $app) {
            return;
        }

        try {
            // Fetch news from Steam API
            $news = $this->fetchNewsFromApi($app->appid);

            // Update the last_news_update field in the SteamApp model
            $app->update(['last_news_update' => Carbon::now()]);

            // Store news in the database
            DB::beginTransaction();
            try {
                $this->storeSteamAppNews($app, $news['newsitems']);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $errorMessage = "Failed to store news for app {$app->name} (appid: {$app->appid}): {$e->getMessage()}";
                throw new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e);
            }
        } catch (Exception $e) {
            $errorMessage = "Error processing app {$app->name} (appid: {$app->appid}): {$e->getMessage()}";
            throw new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Fetch news for a Steam app from the Steam API.
     *
     * @throws Exception
     */
    private function fetchNewsFromApi(int $appid): ?array
    {
        $response = Http::get(self::STEAM_API_URL, [
            'appid' => $appid,
            'count' => 100,
            'maxlength' => 0,
            'format' => 'json',
        ]);

        if (! $response->successful()) {
            throw new Exception("FetchNewsFromApi, Steam API response status: {$response->status()}");
        }

        $data = $response->json();

        if (! isset($data['appnews']) || ! isset($data['appnews']['newsitems'])) {
            throw new Exception("FetchNewsFromApi, Steam API response body: {$response->body()}");
        }

        return $data['appnews'];
    }

    /**
     * Store Steam app news in the database.
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
