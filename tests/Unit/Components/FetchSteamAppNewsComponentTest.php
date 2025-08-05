<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Components;

use Artryazanov\LaravelSteamAppsDb\Components\FetchSteamAppNewsComponent;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use ReflectionMethod;

class FetchSteamAppNewsComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mock command for testing
     */
    private $mockCommand;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock command
        $this->mockCommand = Mockery::mock(Command::class);
        $this->mockCommand->shouldReceive('info')->andReturnSelf();
        $this->mockCommand->shouldReceive('line')->andReturnSelf();
        $this->mockCommand->shouldReceive('warn')->andReturnSelf();
        $this->mockCommand->shouldReceive('error')->andReturnSelf();
    }

    /**
     * Test that the storeSteamAppNews method correctly handles news items.
     */
    public function testStoreSteamAppNews(): void
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
        $component = new FetchSteamAppNewsComponent();
        $method = new ReflectionMethod($component, 'storeSteamAppNews');
        $method->setAccessible(true);
        $method->invoke($component, $app, $newNewsItems);

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
     * Test that the fetchNewsFromApi method correctly fetches news from the Steam API.
     */
    public function testFetchNewsFromApi(): void
    {
        // Mock the HTTP response
        Http::fake([
            'api.steampowered.com/ISteamNews/GetNewsForApp/v0002/*' => Http::response([
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
                        ]
                    ]
                ]
            ]),
        ]);

        // Create an instance of the component and call the fetchNewsFromApi method
        $component = new FetchSteamAppNewsComponent();
        $method = new ReflectionMethod($component, 'fetchNewsFromApi');
        $method->setAccessible(true);
        $result = $method->invoke($component, 570, $this->mockCommand);

        // Verify the result
        $this->assertIsArray($result);
        $this->assertEquals(570, $result['appid']);
        $this->assertCount(1, $result['newsitems']);
        $this->assertEquals('Test News', $result['newsitems'][0]['title']);
    }

    /**
     * Test that the getSteamAppsToProcess method correctly returns apps to process
     * based on their last_news_update status and the specified limit.
     */
    public function testGetSteamAppsToProcess(): void
    {
        // Create an instance of the component and get access to the private method
        $component = new FetchSteamAppNewsComponent();
        $method = new ReflectionMethod($component, 'getSteamAppsToProcess');
        $method->setAccessible(true);

        // Define the one month ago date that's used in the method
        $oneMonthAgo = Carbon::now()->subMonth();

        // Test Case 1: No apps in the database
        // Should return an empty collection
        $result = $method->invoke($component, 5);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);

        // Test Case 2: Only apps with recent news (less than a month old)
        // Should return an empty collection as these aren't processed
        SteamApp::factory()->count(3)->create([
            'last_news_update' => Carbon::now()->subDays(5),
        ]);

        $result = $method->invoke($component, 5);
        $this->assertCount(0, $result);

        // Test Case 3: Only apps with no news
        // Should return all apps with no news up to the limit
        SteamApp::query()->delete(); // Clear previous test data

        $appsWithNoNews = SteamApp::factory()->count(5)->create([
            'last_news_update' => null,
        ]);

        // Test with limit less than available apps
        $result = $method->invoke($component, 3);
        $this->assertCount(3, $result);

        // Test with limit equal to available apps
        $result = $method->invoke($component, 5);
        $this->assertCount(5, $result);

        // Test with limit greater than available apps
        $result = $method->invoke($component, 10);
        $this->assertCount(5, $result);

        // Test Case 4: Only apps with old news
        // Should return all apps with old news up to the limit
        SteamApp::query()->delete(); // Clear previous test data

        $appsWithOldNews = SteamApp::factory()->count(5)->create([
            'last_news_update' => $oneMonthAgo->subDays(5), // Older than one month
        ]);

        $result = $method->invoke($component, 3);
        $this->assertCount(3, $result);

        // Test Case 5: Mix of apps with no news, old news, and recent news
        // Should prioritize apps with no news, then apps with old news
        SteamApp::query()->delete(); // Clear previous test data

        // Create 3 apps with no news (highest priority)
        $appsWithNoNews = SteamApp::factory()->count(3)->create([
            'last_news_update' => null,
        ]);

        // Create 3 apps with old news (medium priority)
        $appsWithOldNews = SteamApp::factory()->count(3)->create([
            'last_news_update' => $oneMonthAgo->subDays(5), // Older than one month
        ]);

        // Create 3 apps with recent news (not processed)
        $appsWithRecentNews = SteamApp::factory()->count(3)->create([
            'last_news_update' => Carbon::now()->subDays(5), // Less than one month
        ]);

        // Test with limit less than apps with no news
        $result = $method->invoke($component, 2);
        $this->assertCount(2, $result);
        // All results should be apps with no news
        foreach ($result as $app) {
            $this->assertNull($app->last_news_update);
        }

        // Test with limit equal to apps with no news
        $result = $method->invoke($component, 3);
        $this->assertCount(3, $result);
        // All results should be apps with no news
        foreach ($result as $app) {
            $this->assertNull($app->last_news_update);
        }

        // Test with limit between (apps with no news) and (apps with no news + apps with old news)
        $result = $method->invoke($component, 5);
        $this->assertCount(5, $result);
        // Should include all 3 apps with no news and 2 apps with old news
        $appsWithNoNewsCount = 0;
        $appsWithOldNewsCount = 0;

        foreach ($result as $app) {
            if ($app->last_news_update === null) {
                $appsWithNoNewsCount++;
            } elseif ($app->last_news_update < $oneMonthAgo) {
                $appsWithOldNewsCount++;
            }
        }

        $this->assertEquals(3, $appsWithNoNewsCount);
        $this->assertEquals(2, $appsWithOldNewsCount);

        // Test with limit greater than (apps with no news + apps with old news)
        $result = $method->invoke($component, 10);
        $this->assertCount(6, $result); // Should only return 6 apps (3 with no news + 3 with old news)

        $appsWithNoNewsCount = 0;
        $appsWithOldNewsCount = 0;

        foreach ($result as $app) {
            if ($app->last_news_update === null) {
                $appsWithNoNewsCount++;
            } elseif ($app->last_news_update < $oneMonthAgo) {
                $appsWithOldNewsCount++;
            }
        }

        $this->assertEquals(3, $appsWithNoNewsCount);
        $this->assertEquals(3, $appsWithOldNewsCount);
    }

    /**
     * Test that the fetchSteamAppNews method correctly fetches and stores news.
     */
    public function testFetchSteamAppNews(): void
    {
        // Create a Steam app
        $steamApp = SteamApp::factory()->create([
            'appid' => 570,
            'name' => 'Test App',
        ]);

        // Mock the HTTP response
        Http::fake([
            'api.steampowered.com/ISteamNews/GetNewsForApp/v0002/*' => Http::response([
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
                        ]
                    ]
                ]
            ]),
        ]);

        // Create an instance of the component and call the fetchSteamAppNews method
        $component = new FetchSteamAppNewsComponent();
        $component->fetchSteamAppNews(1, null, $this->mockCommand);

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
