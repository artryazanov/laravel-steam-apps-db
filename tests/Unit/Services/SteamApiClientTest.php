<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Services;

use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class SteamApiClientTest extends TestCase
{
    public function test_get_app_details_returns_null_when_success_is_false(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '123456' => [
                    'success' => false,
                ],
            ], 200),
        ]);

        $client = new SteamApiClient;
        $result = $client->getAppDetails(123456);

        $this->assertNull($result);
    }

    public function test_get_app_details_throws_on_http_error(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([], 500),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getAppDetails(123456);
    }

    public function test_get_app_details_throws_when_success_key_is_missing(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '123456' => [],
            ], 200),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getAppDetails(123456);
    }

    public function test_get_app_details_throws_when_response_is_malformed(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '123456' => [
                    'success' => true,
                    // missing 'data'
                ],
            ], 200),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getAppDetails(123456);
    }

    public function test_get_app_news_throws_on_http_error(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamNews/GetNewsForApp/v2/*' => Http::response([], 502),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getAppNews(570);
    }

    public function test_get_app_news_throws_on_invalid_body(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamNews/GetNewsForApp/v2/*' => Http::response(['appnews' => []], 200),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getAppNews(570);
    }

    public function test_get_app_workshop_items_fetches_data_with_api_key(): void
    {
        config(['laravel-steam-apps-db.api_key' => 'secret-steam-key']);

        Http::fake([
            'api.steampowered.com/IPublishedFileService/QueryFiles/v1/*' => Http::response([
                'response' => [
                    'total' => 1,
                    'publishedfiledetails' => [
                        ['publishedfileid' => '111', 'title' => 'Workshop Mod', 'result' => 1],
                    ],
                    'next_cursor' => 'cursor_2',
                ],
            ], 200),
        ]);

        $client = new SteamApiClient;
        $response = $client->getAppWorkshopItems(4000, '*', 50);

        $this->assertSame('cursor_2', $response['next_cursor']);
        $this->assertCount(1, $response['publishedfiledetails']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'IPublishedFileService/QueryFiles/v1/')
                && $request['appid'] === 4000
                && $request['cursor'] === '*'
                && $request['numperpage'] === 50
                && $request['key'] === 'secret-steam-key';
        });
    }

    public function test_get_app_workshop_items_throws_on_http_error(): void
    {
        Http::fake([
            'api.steampowered.com/IPublishedFileService/QueryFiles/v1/*' => Http::response([], 500),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getAppWorkshopItems(4000);
    }

    public function test_get_app_workshop_items_throws_on_invalid_body(): void
    {
        Http::fake([
            'api.steampowered.com/IPublishedFileService/QueryFiles/v1/*' => Http::response(['invalid' => true], 200),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getAppWorkshopItems(4000);
    }

    public function test_get_published_file_details_posts_form_and_returns_details(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/' => Http::response([
                'response' => [
                    'result' => 1,
                    'resultcount' => 2,
                    'publishedfiledetails' => [
                        ['publishedfileid' => '111', 'title' => 'Item 1'],
                        ['publishedfileid' => '222', 'title' => 'Item 2'],
                    ],
                ],
            ], 200),
        ]);

        $client = new SteamApiClient;
        $details = $client->getPublishedFileDetails(['111', '222']);

        $this->assertCount(2, $details);
        $this->assertSame('111', $details[0]['publishedfileid']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/'
                && $request->method() === 'POST'
                && $request['itemcount'] === 2
                && $request['publishedfileids'] === ['111', '222'];
        });
    }

    public function test_get_published_file_details_throws_on_http_error(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/' => Http::response([], 500),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getPublishedFileDetails(['111']);
    }

    public function test_get_published_file_details_throws_on_invalid_body(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/' => Http::response(['response' => []], 200),
        ]);

        $client = new SteamApiClient;

        $this->expectException(LaravelSteamAppsDbException::class);
        $client->getPublishedFileDetails(['111']);
    }

    public function test_get_published_file_details_returns_empty_array_when_no_ids_provided(): void
    {
        Http::fake();

        $client = new SteamApiClient;
        $details = $client->getPublishedFileDetails([]);

        $this->assertSame([], $details);
        Http::assertNothingSent();
    }
}
