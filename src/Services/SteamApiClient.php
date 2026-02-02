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

        if (! isset($data[$appid]['success']) || ! $data[$appid]['success'] || ! isset($data[$appid]['data'])) {
            // It's possible the app doesn't exist or success is false, which isn't always an exception but a "not found" state.
            // However, keeping consistent with previous logic, we might want to throw or return null.
            // Previous component threw exception for body issues.
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
}
