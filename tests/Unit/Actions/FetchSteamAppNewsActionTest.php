<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Actions;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppNewsAction;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;

class FetchSteamAppNewsActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the storeSteamAppNews method correctly handles news items.
     */
    public function test_store_steam_app_news(): void
    {
        // Create a test app
        $app = SteamApp::factory()->create([
            'appid' => 570,
            'name' => 'Test App',
        ]);

        // Create some initial news items
        $initialNewsItems = [
            [
                'gid' => '1',
                'title' => 'News 1',
                'url' => 'http://example.com/news1',
                'is_external_url' => true,
                'author' => 'Author 1',
                'contents' => 'Contents 1',
                'feedlabel' => 'Feed 1',
                'date' => 1700000001,
                'feedname' => 'Feedname 1',
                'feed_type' => 0,
            ],
            [
                'gid' => '2',
                'title' => 'News 2',
                'url' => 'http://example.com/news2',
                'is_external_url' => false,
                'author' => 'Author 2',
                'contents' => 'Contents 2',
                'feedlabel' => 'Feed 2',
                'date' => 1700000002,
                'feedname' => 'Feedname 2',
                'feed_type' => 1,
            ],
        ];

        // Create the initial news items in the database
        foreach ($initialNewsItems as $newsItem) {
            SteamAppNews::create([
                'steam_app_id' => $app->id,
                'gid' => $newsItem['gid'],
                'title' => $newsItem['title'],
                'url' => $newsItem['url'],
                'is_external_url' => $newsItem['is_external_url'],
                'author' => $newsItem['author'],
                'contents' => $newsItem['contents'],
                'feedlabel' => $newsItem['feedlabel'],
                'date' => $newsItem['date'],
                'feedname' => $newsItem['feedname'],
                'feed_type' => $newsItem['feed_type'],
            ]);
        }

        // Verify that the initial news items were created
        $this->assertEquals(2, SteamAppNews::where('steam_app_id', $app->id)->count());

        // Create a new set of news items with some changes:
        // - News 1 is updated
        // - News 2 is unchanged
        // - News 3 is new
        $newNewsItems = [
            [
                'gid' => '1',
                'title' => 'News 1 Updated',
                'url' => 'http://example.com/news1_updated',
                'is_external_url' => true,
                'author' => 'Author 1 Updated',
                'contents' => 'Contents 1 Updated',
                'feedlabel' => 'Feed 1 Updated',
                'date' => 1700000001,
                'feedname' => 'Feedname 1 Updated',
                'feed_type' => 0,
            ],
            [
                'gid' => '2',
                'title' => 'News 2',
                'url' => 'http://example.com/news2',
                'is_external_url' => false,
                'author' => 'Author 2',
                'contents' => 'Contents 2',
                'feedlabel' => 'Feed 2',
                'date' => 1700000002,
                'feedname' => 'Feedname 2',
                'feed_type' => 1,
            ],
            [
                'gid' => '3',
                'title' => 'News 3',
                'url' => 'http://example.com/news3',
                'is_external_url' => true,
                'author' => 'Author 3',
                'contents' => 'Contents 3',
                'feedlabel' => 'Feed 3',
                'date' => 1700000003,
                'feedname' => 'Feedname 3',
                'feed_type' => 2,
                'tags' => ['tag1', 'tag2'],
            ],
        ];

        // Create an instance of the component and call the storeSteamAppNews method
        $action = app(FetchSteamAppNewsAction::class);
        $method = new ReflectionMethod($action, 'storeSteamAppNews');
        $method->setAccessible(true);
        $method->invoke($action, $app, $newNewsItems);

        // Verify that there are now 3 news items (2 existing + 1 new)
        $this->assertEquals(3, SteamAppNews::where('steam_app_id', $app->id)->count());

        // Verify that news item 1 was updated
        $this->assertDatabaseHas('steam_app_news', [
            'steam_app_id' => $app->id,
            'gid' => '1',
            'title' => 'News 1 Updated',
            'url' => 'http://example.com/news1_updated',
            'author' => 'Author 1 Updated',
            'contents' => 'Contents 1 Updated',
            'feedlabel' => 'Feed 1 Updated',
            'feedname' => 'Feedname 1 Updated',
        ]);

        // Verify that news item 2 is unchanged
        $this->assertDatabaseHas('steam_app_news', [
            'steam_app_id' => $app->id,
            'gid' => '2',
            'title' => 'News 2',
            'url' => 'http://example.com/news2',
            'author' => 'Author 2',
            'contents' => 'Contents 2',
            'feedlabel' => 'Feed 2',
            'feedname' => 'Feedname 2',
        ]);

        // Verify that news item 3 was created
        $this->assertDatabaseHas('steam_app_news', [
            'steam_app_id' => $app->id,
            'gid' => '3',
            'title' => 'News 3',
            'url' => 'http://example.com/news3',
            'author' => 'Author 3',
            'contents' => 'Contents 3',
            'feedlabel' => 'Feed 3',
            'feedname' => 'Feedname 3',
            'feed_type' => 2,
        ]);

        // Get the news item 3 and check its tags
        $newsItem3 = SteamAppNews::where('steam_app_id', $app->id)
            ->where('gid', '3')
            ->first();

        $this->assertEquals(['tag1', 'tag2'], $newsItem3->tags);
    }

    /**
     * Test that the fetchSteamAppNews method correctly fetches and stores news.
     */
    public function test_execute_fetches_and_stores_steam_app_news(): void
    {
        // Create a Steam app
        $steamApp = SteamApp::factory()->create([
            'appid' => 570,
            'name' => 'Test App',
        ]);

        // Mock the HTTP response through the Client (or strictly speaking, Http proxy)
        Http::fake([
            'api.steampowered.com/ISteamNews/GetNewsForApp/v2/*' => Http::response([
                'appnews' => [
                    'appid' => 570,
                    'newsitems' => [
                        [
                            'gid' => '1',
                            'title' => 'Test News',
                            'url' => 'http://example.com/news',
                            'is_external_url' => true,
                            'author' => 'Test Author',
                            'contents' => 'Test Contents',
                            'feedlabel' => 'Test Feed',
                            'date' => 1700000000,
                            'feedname' => 'Test Feedname',
                            'feed_type' => 0,
                        ],
                    ],
                ],
            ]),
        ]);

        // Create an instance of the action via container
        $action = app(FetchSteamAppNewsAction::class);
        $action->execute(570);

        // Verify that the news was stored
        $this->assertDatabaseHas('steam_app_news', [
            'steam_app_id' => $steamApp->id,
            'gid' => '1',
            'title' => 'Test News',
        ]);

        // Verify that the last_news_update field was updated
        $steamApp->refresh();
        $this->assertNotNull($steamApp->last_news_update);
    }
}
