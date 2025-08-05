<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Console;

use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppNews;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

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
     * Test that the command fetches news for a specific app when appid option is provided.
     */
    public function testFetchNewsForSpecificApp(): void
    {
        // Create a Steam app with a specific appid
        $specificApp = SteamApp::factory()->create([
            'appid' => 440, // Using Team Fortress 2 appid
            'name' => 'Team Fortress 2',
        ]);

        // Mock the HTTP response for the specific app
        Http::fake([
            'api.steampowered.com/ISteamNews/GetNewsForApp/v0002/*' => Http::response([
                'appnews' => [
                    'appid' => 440,
                    'newsitems' => [
                        [
                            'gid' => '9876543210',
                            'title' => 'Team Fortress 2 Update',
                            'url' => 'https://steamstore-a.akamaihd.net/news/externalpost/tf2_blog/9876543210',
                            'is_external_url' => true,
                            'author' => 'TF2 Team',
                            'contents' => 'Update content',
                            'feedlabel' => 'TF2 Blog',
                            'date' => 1753000000,
                            'feedname' => 'tf2_blog',
                            'feed_type' => 1,
                            'appid' => 440
                        ]
                    ]
                ]
            ]),
        ]);

        // Run the command with the appid option
        $this->artisan('steam:fetch-app-news 1 --appid=440')
            ->expectsOutput('Starting to fetch news for specific Steam app (appid: 440)...')
            ->expectsOutput('Found Steam app: Team Fortress 2 (appid: 440)')
            ->assertExitCode(0);

        // Assert that the news item was stored for the specific app
        $this->assertDatabaseHas('steam_app_news', [
            'steam_app_id' => $specificApp->id,
            'gid' => '9876543210',
            'title' => 'Team Fortress 2 Update',
            'url' => 'https://steamstore-a.akamaihd.net/news/externalpost/tf2_blog/9876543210',
            'is_external_url' => 1,
            'author' => 'TF2 Team',
            'contents' => 'Update content',
            'feedlabel' => 'TF2 Blog',
            'date' => 1753000000,
            'feedname' => 'tf2_blog',
            'feed_type' => 1,
        ]);

        // Assert that the last_news_update field was updated
        $specificApp->refresh();
        $this->assertNotNull($specificApp->last_news_update);
    }
}
