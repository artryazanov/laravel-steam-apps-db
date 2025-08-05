<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Components;

use Artryazanov\LaravelSteamAppsDb\Components\FetchSteamAppDetailsComponent;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppMovie;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppScreenshot;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use ReflectionMethod;

class FetchSteamAppDetailsComponentTest extends TestCase
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

        // Create an instance of the component and call the storeScreenshots method
        $component = new FetchSteamAppDetailsComponent();
        $method = new ReflectionMethod($component, 'storeScreenshots');
        $method->setAccessible(true);
        $method->invoke($component, $app, $newScreenshots);

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

        // Create an instance of the component and call the storeMovies method
        $component = new FetchSteamAppDetailsComponent();
        $method = new ReflectionMethod($component, 'storeMovies');
        $method->setAccessible(true);
        $method->invoke($component, $app, $newMovies);

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

    /**
     * Test that the fetchSteamAppDetails method correctly fetches details for a specific app by appid.
     */
    public function testFetchSteamAppDetailsForSpecificApp(): void
    {
        // Create multiple Steam apps
        $targetApp = SteamApp::factory()->create([
            'appid' => 123456,
            'name' => 'Target App',
        ]);

        $otherApp = SteamApp::factory()->create([
            'appid' => 654321,
            'name' => 'Other App',
        ]);

        // Mock the HTTP response for the target app
        Http::fake([
            'store.steampowered.com/api/appdetails?appids=123456&cc=us&l=en' => Http::response([
                '123456' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Target App',
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
                    ],
                ],
            ]),
            // Any other request will return a failure response
            '*' => Http::response([
                'success' => false,
            ]),
        ]);

        // Create an instance of the component
        $component = new FetchSteamAppDetailsComponent();

        // Call the fetchSteamAppDetails method with a specific appid
        $component->fetchSteamAppDetails(10, '123456', $this->mockCommand);

        // Assert that the details were stored for the target app
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $targetApp->id,
            'name' => 'Target App',
            'type' => 'game',
            'is_free' => 0,
            'required_age' => 0,
            'detailed_description' => 'Test description',
            'about_the_game' => 'About the game',
            'short_description' => 'Short description',
            'supported_languages' => 'English',
            'header_image' => 'http://example.com/header.jpg',
        ]);

        // Assert that the last_details_update field was updated for the target app
        $targetApp->refresh();
        $this->assertNotNull($targetApp->last_details_update);

        // Assert that no details were stored for the other app
        $this->assertDatabaseMissing('steam_app_details', [
            'steam_app_id' => $otherApp->id,
        ]);
    }
}
