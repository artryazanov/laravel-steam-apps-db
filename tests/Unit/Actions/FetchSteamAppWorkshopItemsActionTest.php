<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Actions;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppWorkshopItemsAction;
use Artryazanov\LaravelSteamAppsDb\Actions\ImportSteamAppsAction;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppWorkshopItemsJob;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppWorkshopItem;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
                    ]
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
                ]
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
}
