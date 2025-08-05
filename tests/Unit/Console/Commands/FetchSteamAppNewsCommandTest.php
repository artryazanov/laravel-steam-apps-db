<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Console\Commands;

use Artryazanov\LaravelSteamAppsDb\Console\Commands\FetchSteamAppNewsCommand;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;

class FetchSteamAppNewsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the command fetches and stores Steam app news correctly.
     */
    public function testFetchSteamAppNews(): void
    {
        // Create a Steam app that will have an associated game
        $steamApp = SteamApp::factory()->create([
            'appid' => 570, // Using Dota 2 appid as in the example
            'name' => 'Test App With Game',
        ]);

        // Mock the HTTP response for the app
        Http::fake([
            'api.steampowered.com/ISteamNews/GetNewsForApp/v0002/*' => Http::response([
                'appnews' => [
                    'appid' => 570,
                    'newsitems' => [
                        [
                            'gid' => '1805065414364376',
                            'title' => 'Dota 2 system requirements',
                            'url' => 'https://steamstore-a.akamaihd.net/news/externalpost/PCGamesN/1805065414364376',
                            'is_external_url' => true,
                            'author' => 'editor@pcgamesn.com',
                            'contents' => 'Test content',
                            'feedlabel' => 'PCGamesN',
                            'date' => 1752581747,
                            'feedname' => 'PCGamesN',
                            'feed_type' => 0,
                            'appid' => 570
                        ],
                        [
                            'gid' => '1802893906902262',
                            'title' => '7.39c Gameplay Patch',
                            'url' => 'https://steamstore-a.akamaihd.net/news/externalpost/steam_community_announcements/1802893906902262',
                            'is_external_url' => true,
                            'author' => 'Dota Workshop',
                            'contents' => 'Patch notes content',
                            'feedlabel' => 'Community Announcements',
                            'date' => 1750798840,
                            'feedname' => 'steam_community_announcements',
                            'feed_type' => 1,
                            'appid' => 570,
                            'tags' => [
                                'patchnotes'
                            ]
                        ]
                    ]
                ]
            ]),
            // Mock responses for other apps as needed
            '*' => Http::response([
                'success' => false,
            ]),
        ]);

        // Run the command
        $this->artisan('steam:fetch-app-news 1')
            ->expectsOutput('Starting to fetch Steam app news (count: 1)...')
            ->assertExitCode(0);

        // Assert that the news items were stored for the app
        $this->assertDatabaseHas('steam_app_news', [
            'steam_app_id' => $steamApp->id,
            'gid' => '1805065414364376',
            'title' => 'Dota 2 system requirements',
            'url' => 'https://steamstore-a.akamaihd.net/news/externalpost/PCGamesN/1805065414364376',
            'is_external_url' => 1,
            'author' => 'editor@pcgamesn.com',
            'contents' => 'Test content',
            'feedlabel' => 'PCGamesN',
            'date' => 1752581747,
            'feedname' => 'PCGamesN',
            'feed_type' => 0,
        ]);

        $this->assertDatabaseHas('steam_app_news', [
            'steam_app_id' => $steamApp->id,
            'gid' => '1802893906902262',
            'title' => '7.39c Gameplay Patch',
            'url' => 'https://steamstore-a.akamaihd.net/news/externalpost/steam_community_announcements/1802893906902262',
            'is_external_url' => 1,
            'author' => 'Dota Workshop',
            'contents' => 'Patch notes content',
            'feedlabel' => 'Community Announcements',
            'date' => 1750798840,
            'feedname' => 'steam_community_announcements',
            'feed_type' => 1,
        ]);

        // Assert that the last_news_update field was updated
        $steamApp->refresh();
        $this->assertNotNull($steamApp->last_news_update);
    }

    /**
     * Test that the command prioritizes apps correctly.
     */
    public function testPrioritizesAppsCorrectly(): void
    {
        // Create Steam apps
        // High priority app (with associated game and no news)
        $highPriorityApp = SteamApp::factory()->create([
            'appid' => 570,
            'name' => 'High Priority App',
        ]);

        // Medium priority app (without associated game and no news)
        $mediumPriorityApp = SteamApp::factory()->create([
            'appid' => 730,
            'name' => 'Medium Priority App',
        ]);

        // Low priority app (with associated game and old news)
        $lowPriorityApp = SteamApp::factory()->create([
            'appid' => 440,
            'name' => 'Low Priority App',
        ]);

        // Create old news for the third app
        SteamAppNews::create([
            'steam_app_id' => $lowPriorityApp->id,
            'gid' => '123456789',
            'title' => 'Old News',
            'date' => 1700000000,
        ]);

        // Update the last_news_update field in the SteamApp model
        $lowPriorityApp->update([
            'last_news_update' => Carbon::now()->subMonths(2),
        ]);

        // Mock the HTTP responses for all apps
        Http::fake([
            'api.steampowered.com/ISteamNews/GetNewsForApp/v0002/*' => Http::response([
                'appnews' => [
                    'appid' => 570,
                    'newsitems' => [
                        [
                            'gid' => '1805065414364376',
                            'title' => 'News for High Priority App',
                            'date' => 1752581747,
                            'feedname' => 'PCGamesN',
                            'feed_type' => 0,
                            'appid' => 570
                        ]
                    ]
                ]
            ]),
        ]);

        // Run the command with a count of 1 to test that it processes the highest priority app first
        $this->artisan('steam:fetch-app-news 1')
            ->expectsOutput('Starting to fetch Steam app news (count: 1)...')
            ->assertExitCode(0);

        // Assert that the high priority app was processed (it has associated game and no news)
        $this->assertDatabaseHas('steam_app_news', [
            'steam_app_id' => $highPriorityApp->id,
            'title' => 'News for High Priority App',
        ]);

        // Assert that the last_news_update field was updated for the high priority app
        $highPriorityApp->refresh();
        $this->assertNotNull($highPriorityApp->last_news_update);
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

        // Create an instance of the command and call the storeSteamAppNews method
        $command = new FetchSteamAppNewsCommand();
        $method = new \ReflectionMethod($command, 'storeSteamAppNews');
        $method->setAccessible(true);
        $method->invoke($command, $app, $newNewsItems);

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
}
