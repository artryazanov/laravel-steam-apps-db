<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Console\Commands;

use Artryazanov\LaravelSteamAppsDb\Console\Commands\FetchSteamAppDetailsCommand;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDetail;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppMovie;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppScreenshot;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class FetchSteamAppDetailsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the command fetches and stores Steam game details correctly.
     */
    public function testFetchSteamGameDetails(): void
    {
        // Create a Steam app that will have an associated game
        $steamAppWithGame = SteamApp::factory()->create([
            'appid' => 123456,
            'name' => 'Test App With Game',
        ]);

        // Create a Steam app without an associated game
        SteamApp::factory()->create([
            'appid' => 654321,
            'name' => 'Test App Without Game',
        ]);

        // Create a Steam app with associated game and old details
        $steamAppWithOldDetails = SteamApp::factory()->create([
            'appid' => 789012,
            'name' => 'Test App With Old Details',
        ]);

        // Create old details for the third app
        SteamAppDetail::factory()->create([
            'steam_app_id' => $steamAppWithOldDetails->id,
            'name' => 'Test App With Old Details',
        ]);

        // Update the last_details_update field in the SteamApp model
        $steamAppWithOldDetails->update([
            'last_details_update' => Carbon::now()->subYears(2),
        ]);

        // Mock the HTTP response for the first app
        Http::fake([
            'store.steampowered.com/api/appdetails?appids=123456&cc=us&l=en' => Http::response([
                '123456' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Test App With Game',
                        'steam_appid' => 123456,
                        'required_age' => 0,
                        'is_free' => false,
                        'detailed_description' => 'Test description',
                        'about_the_game' => 'About the game',
                        'short_description' => 'Short description',
                        'supported_languages' => 'English',
                        'header_image' => 'http://example.com/header.jpg',
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                        'release_date' => [
                            'coming_soon' => false,
                            'date' => '2023-01-01',
                        ],
                        'support_info' => [
                            'url' => 'http://example.com/support',
                            'email' => 'support@example.com',
                        ],
                        'categories' => [
                            ['id' => 1, 'description' => 'Single-player'],
                            ['id' => 2, 'description' => 'Multi-player'],
                        ],
                        'genres' => [
                            ['id' => '1', 'description' => 'Action'],
                            ['id' => '2', 'description' => 'Adventure'],
                        ],
                        'developers' => ['Developer 1', 'Developer 2'],
                        'publishers' => ['Publisher 1'],
                        'price_overview' => [
                            'currency' => 'USD',
                            'initial' => 1999,
                            'final' => 1499,
                            'discount_percent' => 25,
                            'initial_formatted' => '$19.99',
                            'final_formatted' => '$14.99',
                        ],
                    ],
                ],
            ]),
            // Mock responses for other apps as needed
            '*' => Http::response([
                'success' => false,
            ]),
        ]);

        // Run the command
        $this->artisan('steam:fetch-app-details 1')
            ->expectsOutput('Starting to fetch Steam game details (count: 1)...')
            ->assertExitCode(0);

        // Assert that the details were stored for the first app
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $steamAppWithGame->id,
            'name' => 'Test App With Game',
            'type' => 'game',
            'is_free' => 0,
            'required_age' => 0,
            'detailed_description' => 'Test description',
            'about_the_game' => 'About the game',
            'short_description' => 'Short description',
            'supported_languages' => 'English',
            'header_image' => 'http://example.com/header.jpg',
            'windows' => 1,
            'mac' => 0,
            'linux' => 0,
            'support_url' => 'http://example.com/support',
            'support_email' => 'support@example.com',
        ]);

        // Assert that categories were stored
        $steamAppWithGame->refresh();
        $this->assertEquals(2, $steamAppWithGame->categories()->count());

        // Verify that the specific categories from the mock data are associated with the app
        $categories = $steamAppWithGame->categories;
        $categoryIds = $categories->pluck('category_id')->toArray();
        $categoryDescriptions = $categories->pluck('description')->toArray();
        $this->assertContains(1, $categoryIds);
        $this->assertContains(2, $categoryIds);
        $this->assertContains('Single-player', $categoryDescriptions);
        $this->assertContains('Multi-player', $categoryDescriptions);

        $this->assertEquals(2, $steamAppWithGame->genres()->count());

        // Verify that the specific genres from the mock data are associated with the app
        $genres = $steamAppWithGame->genres;
        $genreIds = $genres->pluck('genre_id')->toArray();
        $genreDescriptions = $genres->pluck('description')->toArray();
        $this->assertContains('1', $genreIds);
        $this->assertContains('2', $genreIds);
        $this->assertContains('Action', $genreDescriptions);
        $this->assertContains('Adventure', $genreDescriptions);

        // Verify that the specific developers from the mock data are associated with the app
        $this->assertEquals(2, $steamAppWithGame->developers()->count());
        $developers = $steamAppWithGame->developers;
        $developerNames = $developers->pluck('name')->toArray();
        $this->assertContains('Developer 1', $developerNames);
        $this->assertContains('Developer 2', $developerNames);

        // Verify that the specific publisher from the mock data is associated with the app
        $this->assertEquals(1, $steamAppWithGame->publishers()->count());
        $publishers = $steamAppWithGame->publishers;
        $publisherNames = $publishers->pluck('name')->toArray();
        $this->assertContains('Publisher 1', $publisherNames);

        // Assert that price info was stored
        $this->assertDatabaseHas('steam_app_price_info', [
            'steam_app_id' => $steamAppWithGame->id,
            'currency' => 'USD',
            'initial' => 1999,
            'final' => 1499,
            'discount_percent' => 25,
            'initial_formatted' => '$19.99',
            'final_formatted' => '$14.99',
        ]);
    }

    /**
     * Test that the command prioritizes apps correctly.
     */
    public function testPrioritizesAppsCorrectly(): void
    {
        // Create Steam apps
        // High priority app (with associated game and no details)
        $highPriorityApp = SteamApp::factory()->create([
            'appid' => 123456,
            'name' => 'High Priority App',
        ]);

        // Medium priority app (without associated game and no details)
        $mediumPriorityApp = SteamApp::factory()->create([
            'appid' => 654321,
            'name' => 'Medium Priority App',
        ]);

        // Low priority app (with associated game and old details)
        $lowPriorityApp = SteamApp::factory()->create([
            'appid' => 789012,
            'name' => 'Low Priority App',
        ]);

        // Create old details for the third app
        SteamAppDetail::factory()->create([
            'steam_app_id' => $lowPriorityApp->id,
            'name' => 'Low Priority App',
        ]);

        // Update the last_details_update field in the SteamApp model
        $lowPriorityApp->update([
            'last_details_update' => Carbon::now()->subYears(2),
        ]);

        // Mock the HTTP responses for all apps
        Http::fake([
            'store.steampowered.com/api/appdetails?appids=123456&cc=us&l=en' => Http::response([
                '123456' => [
                    'success' => true,
                    'data' => [
                        'name' => 'High Priority App',
                        'steam_appid' => 123456,
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                    ],
                ],
            ]),
            'store.steampowered.com/api/appdetails?appids=654321&cc=us&l=en' => Http::response([
                '654321' => [
                    'success' => true,
                    'data' => [
                        'name' => 'Medium Priority App',
                        'steam_appid' => 654321,
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                    ],
                ],
            ]),
            'store.steampowered.com/api/appdetails?appids=789012&cc=us&l=en' => Http::response([
                '789012' => [
                    'success' => true,
                    'data' => [
                        'name' => 'Low Priority App',
                        'steam_appid' => 789012,
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                    ],
                ],
            ]),
        ]);

        // Run the command with a count of 1 to test that it processes the highest priority app first
        $this->artisan('steam:fetch-app-details 1')
            ->expectsOutput('Starting to fetch Steam game details (count: 1)...')
            ->assertExitCode(0);

        // Assert that the high priority app was processed (it has associated game and no details)
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $highPriorityApp->id,
            'name' => 'High Priority App',
        ]);

        // Run the command again with a count of 1 to test that it processes the medium priority app next
        $this->artisan('steam:fetch-app-details 1')
            ->expectsOutput('Starting to fetch Steam game details (count: 1)...')
            ->assertExitCode(0);

        // Assert that the medium priority app was processed (it has no associated game and no details)
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $mediumPriorityApp->id,
            'name' => 'Medium Priority App',
        ]);

        // Run the command again with a count of 1 to test that it processes the low priority app last
        $this->artisan('steam:fetch-app-details 1')
            ->expectsOutput('Starting to fetch Steam game details (count: 1)...')
            ->assertExitCode(0);

        // Assert that the low priority app was processed (it has associated game and old details)
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $lowPriorityApp->id,
            'name' => 'Low Priority App',
        ]);

        // Assert that the last_details_update field was updated for the low priority app
        $lowPriorityApp->refresh();
        $this->assertNotNull($lowPriorityApp->last_details_update);
        $this->assertTrue($lowPriorityApp->last_details_update->gt(Carbon::now()->subYears(2)));
    }

    /**
     * Test that the storeScreenshots method correctly handles screenshots.
     */
    public function testStoreScreenshots(): void
    {
        // Create a test app
        $app = SteamApp::factory()->create([
            'appid' => 123456,
            'name' => 'Test App',
        ]);

        // Create some initial screenshots
        $initialScreenshots = [
            [
                'id' => 1,
                'path_thumbnail' => 'http://example.com/thumb1.jpg',
                'path_full' => 'http://example.com/full1.jpg',
            ],
            [
                'id' => 2,
                'path_thumbnail' => 'http://example.com/thumb2.jpg',
                'path_full' => 'http://example.com/full2.jpg',
            ],
            [
                'id' => 3,
                'path_thumbnail' => 'http://example.com/thumb3.jpg',
                'path_full' => 'http://example.com/full3.jpg',
            ],
        ];

        // Create the initial screenshots in the database
        foreach ($initialScreenshots as $screenshot) {
            SteamAppScreenshot::create([
                'steam_app_id' => $app->id,
                'screenshot_id' => $screenshot['id'],
                'path_thumbnail' => $screenshot['path_thumbnail'],
                'path_full' => $screenshot['path_full'],
            ]);
        }

        // Verify that the initial screenshots were created
        $this->assertEquals(3, SteamAppScreenshot::where('steam_app_id', $app->id)->count());

        // Create a new set of screenshots with some changes:
        // - Screenshot 1 is updated
        // - Screenshot 2 is unchanged
        // - Screenshot 3 is removed
        // - Screenshot 4 is new
        $newScreenshots = [
            [
                'id' => 1,
                'path_thumbnail' => 'http://example.com/thumb1_updated.jpg',
                'path_full' => 'http://example.com/full1_updated.jpg',
            ],
            [
                'id' => 2,
                'path_thumbnail' => 'http://example.com/thumb2.jpg',
                'path_full' => 'http://example.com/full2.jpg',
            ],
            [
                'id' => 4,
                'path_thumbnail' => 'http://example.com/thumb4.jpg',
                'path_full' => 'http://example.com/full4.jpg',
            ],
        ];

        // Create an instance of the command and call the storeScreenshots method
        $command = new FetchSteamAppDetailsCommand();
        $method = new \ReflectionMethod($command, 'storeScreenshots');
        $method->setAccessible(true);
        $method->invoke($command, $app, $newScreenshots);

        // Verify that there are still 3 screenshots (2 kept, 1 new)
        $this->assertEquals(3, SteamAppScreenshot::where('steam_app_id', $app->id)->count());

        // Verify that screenshot 1 was updated
        $this->assertDatabaseHas('steam_app_screenshots', [
            'steam_app_id' => $app->id,
            'screenshot_id' => 1,
            'path_thumbnail' => 'http://example.com/thumb1_updated.jpg',
            'path_full' => 'http://example.com/full1_updated.jpg',
        ]);

        // Verify that screenshot 2 is unchanged
        $this->assertDatabaseHas('steam_app_screenshots', [
            'steam_app_id' => $app->id,
            'screenshot_id' => 2,
            'path_thumbnail' => 'http://example.com/thumb2.jpg',
            'path_full' => 'http://example.com/full2.jpg',
        ]);

        // Verify that screenshot 3 was soft deleted
        $this->assertEquals(1, SteamAppScreenshot::withTrashed()
            ->where('steam_app_id', $app->id)
            ->where('screenshot_id', 3)
            ->whereNotNull('deleted_at')
            ->count());

        // Verify that screenshot 4 was created
        $this->assertDatabaseHas('steam_app_screenshots', [
            'steam_app_id' => $app->id,
            'screenshot_id' => 4,
            'path_thumbnail' => 'http://example.com/thumb4.jpg',
            'path_full' => 'http://example.com/full4.jpg',
        ]);
    }

    /**
     * Test that the storeMovies method correctly handles movies.
     */
    public function testStoreMovies(): void
    {
        // Create a test app
        $app = SteamApp::factory()->create([
            'appid' => 123456,
            'name' => 'Test App',
        ]);

        // Create some initial movies
        $initialMovies = [
            [
                'id' => 1,
                'name' => 'Movie 1',
                'thumbnail' => 'http://example.com/thumb1.jpg',
                'webm' => [
                    '480' => 'http://example.com/webm_480_1.webm',
                    'max' => 'http://example.com/webm_max_1.webm',
                ],
                'mp4' => [
                    '480' => 'http://example.com/mp4_480_1.mp4',
                    'max' => 'http://example.com/mp4_max_1.mp4',
                ],
                'highlight' => true,
            ],
            [
                'id' => 2,
                'name' => 'Movie 2',
                'thumbnail' => 'http://example.com/thumb2.jpg',
                'webm' => [
                    '480' => 'http://example.com/webm_480_2.webm',
                    'max' => 'http://example.com/webm_max_2.webm',
                ],
                'mp4' => [
                    '480' => 'http://example.com/mp4_480_2.mp4',
                    'max' => 'http://example.com/mp4_max_2.mp4',
                ],
                'highlight' => false,
            ],
            [
                'id' => 3,
                'name' => 'Movie 3',
                'thumbnail' => 'http://example.com/thumb3.jpg',
                'webm' => [
                    '480' => 'http://example.com/webm_480_3.webm',
                    'max' => 'http://example.com/webm_max_3.webm',
                ],
                'mp4' => [
                    '480' => 'http://example.com/mp4_480_3.mp4',
                    'max' => 'http://example.com/mp4_max_3.mp4',
                ],
                'highlight' => false,
            ],
        ];

        // Create the initial movies in the database
        foreach ($initialMovies as $movie) {
            SteamAppMovie::create([
                'steam_app_id' => $app->id,
                'movie_id' => $movie['id'],
                'name' => $movie['name'],
                'thumbnail' => $movie['thumbnail'],
                'webm_480' => $movie['webm']['480'],
                'webm_max' => $movie['webm']['max'],
                'mp4_480' => $movie['mp4']['480'],
                'mp4_max' => $movie['mp4']['max'],
                'highlight' => $movie['highlight'],
            ]);
        }

        // Verify that the initial movies were created
        $this->assertEquals(3, SteamAppMovie::where('steam_app_id', $app->id)->count());

        // Create a new set of movies with some changes:
        // - Movie 1 is updated
        // - Movie 2 is unchanged
        // - Movie 3 is removed
        // - Movie 4 is new
        $newMovies = [
            [
                'id' => 1,
                'name' => 'Movie 1 Updated',
                'thumbnail' => 'http://example.com/thumb1_updated.jpg',
                'webm' => [
                    '480' => 'http://example.com/webm_480_1_updated.webm',
                    'max' => 'http://example.com/webm_max_1_updated.webm',
                ],
                'mp4' => [
                    '480' => 'http://example.com/mp4_480_1_updated.mp4',
                    'max' => 'http://example.com/mp4_max_1_updated.mp4',
                ],
                'highlight' => false,
            ],
            [
                'id' => 2,
                'name' => 'Movie 2',
                'thumbnail' => 'http://example.com/thumb2.jpg',
                'webm' => [
                    '480' => 'http://example.com/webm_480_2.webm',
                    'max' => 'http://example.com/webm_max_2.webm',
                ],
                'mp4' => [
                    '480' => 'http://example.com/mp4_480_2.mp4',
                    'max' => 'http://example.com/mp4_max_2.mp4',
                ],
                'highlight' => false,
            ],
            [
                'id' => 4,
                'name' => 'Movie 4',
                'thumbnail' => 'http://example.com/thumb4.jpg',
                'webm' => [
                    '480' => 'http://example.com/webm_480_4.webm',
                    'max' => 'http://example.com/webm_max_4.webm',
                ],
                'mp4' => [
                    '480' => 'http://example.com/mp4_480_4.mp4',
                    'max' => 'http://example.com/mp4_max_4.mp4',
                ],
                'highlight' => true,
            ],
        ];

        // Create an instance of the command and call the storeMovies method
        $command = new FetchSteamAppDetailsCommand();
        $method = new \ReflectionMethod($command, 'storeMovies');
        $method->setAccessible(true);
        $method->invoke($command, $app, $newMovies);

        // Verify that there are still 3 movies (2 kept, 1 new)
        $this->assertEquals(3, SteamAppMovie::where('steam_app_id', $app->id)->count());

        // Verify that movie 1 was updated
        $this->assertDatabaseHas('steam_app_movies', [
            'steam_app_id' => $app->id,
            'movie_id' => 1,
            'name' => 'Movie 1 Updated',
            'thumbnail' => 'http://example.com/thumb1_updated.jpg',
            'webm_480' => 'http://example.com/webm_480_1_updated.webm',
            'webm_max' => 'http://example.com/webm_max_1_updated.webm',
            'mp4_480' => 'http://example.com/mp4_480_1_updated.mp4',
            'mp4_max' => 'http://example.com/mp4_max_1_updated.mp4',
            'highlight' => 0,
        ]);

        // Verify that movie 2 is unchanged
        $this->assertDatabaseHas('steam_app_movies', [
            'steam_app_id' => $app->id,
            'movie_id' => 2,
            'name' => 'Movie 2',
            'thumbnail' => 'http://example.com/thumb2.jpg',
            'webm_480' => 'http://example.com/webm_480_2.webm',
            'webm_max' => 'http://example.com/webm_max_2.webm',
            'mp4_480' => 'http://example.com/mp4_480_2.mp4',
            'mp4_max' => 'http://example.com/mp4_max_2.mp4',
            'highlight' => 0,
        ]);

        // Verify that movie 3 was soft deleted
        $this->assertEquals(1, SteamAppMovie::withTrashed()
            ->where('steam_app_id', $app->id)
            ->where('movie_id', 3)
            ->whereNotNull('deleted_at')
            ->count());

        // Verify that movie 4 was created
        $this->assertDatabaseHas('steam_app_movies', [
            'steam_app_id' => $app->id,
            'movie_id' => 4,
            'name' => 'Movie 4',
            'thumbnail' => 'http://example.com/thumb4.jpg',
            'webm_480' => 'http://example.com/webm_480_4.webm',
            'webm_max' => 'http://example.com/webm_max_4.webm',
            'mp4_480' => 'http://example.com/mp4_480_4.mp4',
            'mp4_max' => 'http://example.com/mp4_max_4.mp4',
            'highlight' => 1,
        ]);
    }
}
