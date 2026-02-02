<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Actions;

use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppWorkshopItem;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FetchSteamAppWorkshopItemsAction
{
    public function __construct(
        protected SteamApiClient $steamApiClient
    ) {}

    /**
     * @return string|null Returns next cursor if available, or null if finished
     */
    public function execute(int $appid, string $cursor = '*'): ?string
    {
        $app = SteamApp::where('appid', $appid)->first();
        if (!$app) {
            return null;
        }

        try {
            // 1. Get items list (QueryFiles)
            $queryData = $this->steamApiClient->getAppWorkshopItems($app->appid, $cursor);
            $queryItems = $queryData['publishedfiledetails'] ?? [];
            $nextCursor = $queryData['next_cursor'] ?? null;

            if (empty($queryItems)) {
                return null;
            }

            // 2. Collect IDs for detailed request
            $idsToFetch = [];
            foreach ($queryItems as $item) {
                // result 1 = Success
                if (($item['result'] ?? 1) == 1) {
                    $idsToFetch[] = $item['publishedfileid'];
                }
            }

            // 3. Request full details (GetPublishedFileDetails)
            $detailsItems = $this->steamApiClient->getPublishedFileDetails($idsToFetch);
            $detailsMap = collect($detailsItems)->keyBy('publishedfileid');

            DB::transaction(function () use ($app, $queryItems, $detailsMap) {
                foreach ($queryItems as $queryItem) {
                    // Skip items with errors
                    if (($queryItem['result'] ?? 1) != 1) continue;

                    $id = $queryItem['publishedfileid'];
                    $detailItem = $detailsMap->get($id);

                    // If no details (rare case), skip or create with what we have
                    if (!$detailItem) continue;

                    // Handle tags
                    $tagsRaw = $detailItem['tags'] ?? [];
                    $tags = [];
                    if (is_array($tagsRaw)) {
                        foreach ($tagsRaw as $t) {
                            if (isset($t['tag'])) {
                                $tags[] = $t['tag'];
                            }
                        }
                    }

                    SteamAppWorkshopItem::updateOrCreate(
                        ['publishedfileid' => $id],
                        [
                            'steam_app_id' => $app->id,
                            
                            // Authors and Title
                            'creator' => $detailItem['creator'] ?? null,
                            'title' => $queryItem['title'] ?? ($detailItem['title'] ?? 'Untitled'),
                            
                            // Descriptions
                            'short_description' => $queryItem['short_description'] ?? null,
                            'description' => $detailItem['description'] ?? null,
                            
                            // Files
                            'filename' => $detailItem['filename'] ?? null,
                            'file_size' => $detailItem['file_size'] ?? 0,
                            'file_url' => $detailItem['file_url'] ?? null,
                            'preview_url' => $queryItem['preview_url'] ?? ($detailItem['preview_url'] ?? null),
                            'url' => "https://steamcommunity.com/sharedfiles/filedetails/?id={$id}",
                            
                            // Metadata
                            'tags' => !empty($tags) ? $tags : null,
                            'banned' => (bool)($detailItem['banned'] ?? false),
                            
                            // Statistics
                            'views' => $queryItem['views'] ?? ($detailItem['views'] ?? 0),
                            'subscriptions' => $queryItem['subscriptions'] ?? ($detailItem['subscriptions'] ?? 0),
                            'favorited' => $queryItem['favorited'] ?? ($detailItem['favorited'] ?? 0),
                            'num_comments_public' => $detailItem['num_comments_public'] ?? 0,

                            // Dates
                            'time_created' => isset($queryItem['time_created']) ? Carbon::createFromTimestamp($queryItem['time_created']) : null,
                            'time_updated' => isset($queryItem['time_updated']) ? Carbon::createFromTimestamp($queryItem['time_updated']) : null,
                        ]
                    );
                }
            });

            // Check if finished
            if ($nextCursor === $cursor) {
                return null;
            }

            return $nextCursor;

        } catch (\Exception $e) {
            throw new LaravelSteamAppsDbException("Workshop fetch error for app {$appid}: {$e->getMessage()}", 0, $e);
        }
    }
}
