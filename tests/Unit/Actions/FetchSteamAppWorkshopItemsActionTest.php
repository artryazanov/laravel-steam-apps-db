<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Actions;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppWorkshopItemsAction;
use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppWorkshopItem;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;

class FetchSteamAppWorkshopItemsActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_it_fetches_and_saves_workshop_items_correctly()
    {
        // 1. Setup Data
        $steamApp = SteamApp::factory()->create([
            'appid' => 4000,
            'name' => 'Garrys Mod',
        ]);

        // 2. Mock SteamApiClient
        $mockClient = Mockery::mock(SteamApiClient::class);

        // Mock getAppWorkshopItems (QueryFiles)
        $mockClient->shouldReceive('getAppWorkshopItems')
            ->once()
            ->with(4000, '*')
            ->andReturn([
                'publishedfiledetails' => [
                    [
                        'publishedfileid' => '12345',
                        'title' => 'Test Item',
                        'result' => 1,
                        'views' => 100,
                    ],
                    [
                        'publishedfileid' => '67890',
                        'title' => 'Another Item',
                        'result' => 1,
                    ],
                ],
                'next_cursor' => 'cursor_next_page',
            ]);

        // Mock getPublishedFileDetails (Details)
        $mockClient->shouldReceive('getPublishedFileDetails')
            ->once()
            ->with(['12345', '67890'])
            ->andReturn([
                [
                    'publishedfileid' => '12345',
                    'title' => 'Test Item Full',
                    'description' => 'Full Description',
                    'file_size' => 1024,
                    'creator' => '76561198000000001',
                    'tags' => [['tag' => 'Addon'], ['tag' => 'Map']],
                ],
                [
                    'publishedfileid' => '67890',
                    'title' => 'Another Item Full',
                    'description' => 'Desc 2',
                ],
            ]);

        // 3. Bind Mock
        $this->app->instance(SteamApiClient::class, $mockClient);

        // 4. Run Action
        $action = app(FetchSteamAppWorkshopItemsAction::class);
        $nextCursor = $action->execute(4000, '*');

        // 5. Assertions
        $this->assertEquals('cursor_next_page', $nextCursor);

        $this->assertDatabaseHas('steam_app_workshop_items', [
            'steam_app_id' => $steamApp->id,
            'publishedfileid' => '12345',
            'title' => 'Test Item', // Depends on logic, logic takes query item title first
            'description' => 'Full Description',
            'file_size' => 1024,
            'creator' => '76561198000000001',
        ]);

        // Verify tags (arrays need specific check or retrieval)
        $item = SteamAppWorkshopItem::where('publishedfileid', '12345')->first();
        $this->assertEquals(['Addon', 'Map'], $item->tags);

        $this->assertDatabaseHas('steam_app_workshop_items', [
            'publishedfileid' => '67890',
            'description' => 'Desc 2',
        ]);
    }

    public function test_it_returns_null_if_no_items_found()
    {
        $steamApp = SteamApp::factory()->create(['appid' => 10]);

        $mockClient = Mockery::mock(SteamApiClient::class);
        $mockClient->shouldReceive('getAppWorkshopItems')
            ->once()
            ->andReturn(['publishedfiledetails' => []]);

        $this->app->instance(SteamApiClient::class, $mockClient);

        $action = app(FetchSteamAppWorkshopItemsAction::class);
        $result = $action->execute(10);

        $this->assertNull($result);
        $this->assertDatabaseCount('steam_app_workshop_items', 0);
    }

    public function test_it_fetches_workshop_items_via_http_fake_with_real_client(): void
    {
        $steamApp = SteamApp::factory()->create([
            'appid' => 4000,
            'name' => 'Garrys Mod',
        ]);

        Http::fake([
            'api.steampowered.com/IPublishedFileService/QueryFiles/v1/*' => Http::response([
                'response' => [
                    'publishedfiledetails' => [
                        [
                            'publishedfileid' => '99999',
                            'title' => 'HTTP Workshop Item',
                            'result' => 1,
                            'views' => 42,
                        ],
                    ],
                    'next_cursor' => 'cursor_page_2',
                ],
            ], 200),
            'api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/' => Http::response([
                'response' => [
                    'publishedfiledetails' => [
                        [
                            'publishedfileid' => '99999',
                            'title' => 'HTTP Workshop Item',
                            'description' => 'Detailed Body',
                            'file_size' => 2048,
                            'creator' => '76561198000000002',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $action = app(FetchSteamAppWorkshopItemsAction::class);
        $nextCursor = $action->execute(4000, '*');

        $this->assertSame('cursor_page_2', $nextCursor);
        $this->assertDatabaseHas('steam_app_workshop_items', [
            'steam_app_id' => $steamApp->id,
            'publishedfileid' => 99999,
            'title' => 'HTTP Workshop Item',
            'description' => 'Detailed Body',
            'file_size' => 2048,
        ]);
    }

    public function test_it_returns_null_when_app_not_found_or_cursor_unchanged_or_items_invalid(): void
    {
        $action = app(FetchSteamAppWorkshopItemsAction::class);

        // App does not exist in DB
        $this->assertNull($action->execute(999999, '*'));

        SteamApp::factory()->create(['appid' => 4000, 'name' => 'GMod']);

        // All items have result != 1 -> idsToFetch is empty
        $mockClient = Mockery::mock(SteamApiClient::class);
        $mockClient->shouldReceive('getAppWorkshopItems')
            ->once()
            ->with(4000, '*')
            ->andReturn([
                'publishedfiledetails' => [
                    ['publishedfileid' => '111', 'result' => 9],
                ],
                'next_cursor' => 'next_c',
            ]);
        $this->app->instance(SteamApiClient::class, $mockClient);

        $this->assertSame('next_c', app(FetchSteamAppWorkshopItemsAction::class)->execute(4000, '*'));

        // Cursor unchanged -> returns null
        $mockClient2 = Mockery::mock(SteamApiClient::class);
        $mockClient2->shouldReceive('getAppWorkshopItems')
            ->once()
            ->with(4000, 'same_cursor')
            ->andReturn([
                'publishedfiledetails' => [
                    [
                        'publishedfileid' => '111',
                        'result' => 1,
                        'time_created' => 1700000000,
                        'time_updated' => 1700000100,
                    ],
                ],
                'next_cursor' => 'same_cursor',
            ]);
        $mockClient2->shouldReceive('getPublishedFileDetails')
            ->once()
            ->with(['111'])
            ->andReturn([
                ['publishedfileid' => '111', 'title' => 'Item'],
            ]);
        $this->app->instance(SteamApiClient::class, $mockClient2);

        $this->assertNull(app(FetchSteamAppWorkshopItemsAction::class)->execute(4000, 'same_cursor'));
    }

    public function test_it_wraps_exceptions_in_laravel_steam_apps_db_exception(): void
    {
        SteamApp::factory()->create(['appid' => 4000, 'name' => 'GMod']);

        Http::fake([
            'api.steampowered.com/IPublishedFileService/QueryFiles/v1/*' => Http::response([], 500),
        ]);

        $this->expectException(LaravelSteamAppsDbException::class);
        app(FetchSteamAppWorkshopItemsAction::class)->execute(4000, '*');
    }
}
