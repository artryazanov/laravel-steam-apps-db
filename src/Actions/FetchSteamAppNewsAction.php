<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Actions;

use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchSteamAppNewsAction
{
    public function __construct(
        protected SteamApiClient $steamApiClient
    ) {}

    /**
     * Fetch the latest news for Steam apps and store them in the database.
     *
     * @param  int  $appid  Specific Steam app ID to fetch news for
     *
     * @throws LaravelSteamAppsDbException
     */
    public function execute(int $appid): void
    {
        // Get the specific app by appid
        $app = SteamApp::where('appid', $appid)->first();

        if (! $app) {
            return;
        }

        try {
            // Fetch news from Steam API
            $news = $this->steamApiClient->getAppNews($app->appid);

            // Update the last_news_update field in the SteamApp model
            $app->update(['last_news_update' => Carbon::now()]);

            // Store news in the database
            DB::beginTransaction();
            try {
                $this->storeSteamAppNews($app, $news['newsitems'] ?? []);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $errorMessage = "Failed to store news for app {$app->name} (appid: {$app->appid}): {$e->getMessage()}";
                throw new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e);
            }
        } catch (Exception $e) {
            $errorMessage = "Error processing app {$app->name} (appid: {$app->appid}): {$e->getMessage()}";
            // Log the error instead of stopping everything?
            // The original component re-threw as LaravelSteamAppsDbException
            throw new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e);
        }
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
