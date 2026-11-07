<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Services;

use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Illuminate\Support\Facades\Http;

class SteamApiClient
{
    /**
     * The Steam API endpoint for getting the app list.
     */
    private const STEAM_API_URL = 'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json';

    /**
     * The Steam Store API endpoint for searching apps by title.
     */
    private const STEAM_STORE_SEARCH_URL = 'https://store.steampowered.com/api/storesearch/';

    /**
     * The Steam API endpoint for getting app details.
     */
    private const STEAM_DETAILS_API_URL = 'https://store.steampowered.com/api/appdetails';

    /**
     * The Steam API endpoint for getting app news.
     */
    private const STEAM_APP_NEWS_URL = 'https://api.steampowered.com/ISteamNews/GetNewsForApp/v2/';

    private const STEAM_WORKSHOP_QUERY_URL = 'https://api.steampowered.com/IPublishedFileService/QueryFiles/v1/';

    private const STEAM_REMOTE_STORAGE_URL = 'https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/';

    /**
     * Fetch the list of all Steam apps.
     *
     * @return array<int, array{appid: int, name: string}>
     *
     * @throws LaravelSteamAppsDbException
     */
    public function getAppList(): array
    {
        $response = Http::get(self::STEAM_API_URL);

        if (! $response->successful()) {
            throw new LaravelSteamAppsDbException('Failed to fetch data from Steam API: '.$response->status());
        }

        $data = $response->json();

        if (! isset($data['applist']['apps']) || ! is_array($data['applist']['apps'])) {
            throw new LaravelSteamAppsDbException('Invalid response format from Steam API');
        }

        return $data['applist']['apps'];
    }

    /**
     * Search Steam apps by title via the Steam Store Search API.
     *
     * @return array<int, array{appid: int, name: string}>
     *
     * @throws LaravelSteamAppsDbException
     */
    public function searchAppsByTitle(string $title): array
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            return [];
        }

        $response = Http::get(self::STEAM_STORE_SEARCH_URL, [
            'term' => $trimmed,
            'l' => 'english',
            'cc' => 'US',
        ]);

        if (! $response->successful()) {
            throw new LaravelSteamAppsDbException('Failed to fetch data from Steam API: '.$response->status());
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['items']) || ! is_array($data['items'])) {
            throw new LaravelSteamAppsDbException('Invalid response format from Steam API');
        }

        $apps = [];
        foreach ($data['items'] as $item) {
            if (! is_array($item) || ! isset($item['id'], $item['name'])) {
                continue;
            }

            $apps[] = [
                'appid' => (int) $item['id'],
                'name' => (string) $item['name'],
            ];
        }

        return $apps;
    }

    /**
     * Fetch details for a Steam app from the Steam API.
     *
     * @throws LaravelSteamAppsDbException
     */
    public function getAppDetails(int $appid): ?array
    {
        $response = Http::get(self::STEAM_DETAILS_API_URL, [
            'appids' => $appid,
            'cc' => 'us',
            'l' => 'en',
        ]);

        if (! $response->successful()) {
            throw new LaravelSteamAppsDbException("FetchAppDetailsFromApi, Steam API response status: {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data[$appid]['success'])) {
            throw new LaravelSteamAppsDbException("FetchAppDetailsFromApi, Steam API response body: {$response->body()}");
        }

        if (! $data[$appid]['success']) {
            return null;
        }

        if (! isset($data[$appid]['data']) || ! is_array($data[$appid]['data'])) {
            throw new LaravelSteamAppsDbException("FetchAppDetailsFromApi, Steam API response body: {$response->body()}");
        }

        return $data[$appid]['data'];
    }

    /**
     * Fetch news for a Steam app from the Steam API.
     *
     * @throws LaravelSteamAppsDbException
     */
    public function getAppNews(int $appid): array
    {
        $response = Http::get(self::STEAM_APP_NEWS_URL, [
            'appid' => $appid,
            'count' => 100,
            'maxlength' => 0,
            'format' => 'json',
        ]);

        if (! $response->successful()) {
            throw new LaravelSteamAppsDbException("FetchNewsFromApi, Steam API response status: {$response->status()}");
        }

        $data = $response->json();

        if (! isset($data['appnews']) || ! isset($data['appnews']['newsitems'])) {
            throw new LaravelSteamAppsDbException("FetchNewsFromApi, Steam API response body: {$response->body()}");
        }

        return $data['appnews'];
    }

    /**
     * Fetch Workshop items page for a Steam app via IPublishedFileService/QueryFiles.
     *
     * @throws LaravelSteamAppsDbException
     */
    public function getAppWorkshopItems(int $appid, string $cursor = '*', int $numPerPage = 100): array
    {
        $query = [
            'appid' => $appid,
            'cursor' => $cursor,
            'numperpage' => $numPerPage,
            'return_metadata' => true,
            'return_tags' => true,
            'return_details' => true,
            'return_short_description' => true,
        ];

        $apiKey = config('laravel-steam-apps-db.api_key');
        if (is_string($apiKey) && $apiKey !== '') {
            $query['key'] = $apiKey;
        }

        $response = Http::get(self::STEAM_WORKSHOP_QUERY_URL, $query);

        if (! $response->successful()) {
            throw new LaravelSteamAppsDbException("FetchWorkshopItemsFromApi, Steam API response status: {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['response']) || ! is_array($data['response'])) {
            throw new LaravelSteamAppsDbException("FetchWorkshopItemsFromApi, Steam API response body: {$response->body()}");
        }

        return $data['response'];
    }

    /**
     * Fetch full details for a list of Workshop published file IDs via ISteamRemoteStorage/GetPublishedFileDetails.
     *
     * @param  array<int, int|string>  $publishedFileIds
     * @return array<int, array<string, mixed>>
     *
     * @throws LaravelSteamAppsDbException
     */
    public function getPublishedFileDetails(array $publishedFileIds): array
    {
        $ids = array_values($publishedFileIds);

        if (empty($ids)) {
            return [];
        }

        $response = Http::asForm()->post(self::STEAM_REMOTE_STORAGE_URL, [
            'itemcount' => count($ids),
            'publishedfileids' => $ids,
        ]);

        if (! $response->successful()) {
            throw new LaravelSteamAppsDbException("FetchPublishedFileDetailsFromApi, Steam API response status: {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['response']['publishedfiledetails']) || ! is_array($data['response']['publishedfiledetails'])) {
            throw new LaravelSteamAppsDbException("FetchPublishedFileDetailsFromApi, Steam API response body: {$response->body()}");
        }

        return $data['response']['publishedfiledetails'];
    }
}
