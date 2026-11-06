<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Actions;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppDetailsAction;
use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppDetail;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppMovie;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppPackageGroup;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppScreenshot;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;

class FetchSteamAppDetailsActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the storeScreenshots method correctly handles screenshots.
     */
    public function test_store_screenshots(): void
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

        // Create an instance of the action via container
        $action = app(FetchSteamAppDetailsAction::class);
        $method = new ReflectionMethod($action, 'storeScreenshots');
        $method->setAccessible(true);
        $method->invoke($action, $app, $newScreenshots);

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

        // Re-introduce soft-deleted screenshot 3 and verify it is restored without unique constraint failure
        $restoredScreenshots = [
            [
                'id' => 3,
                'path_thumbnail' => 'http://example.com/thumb3_restored.jpg',
                'path_full' => 'http://example.com/full3_restored.jpg',
            ],
        ];
        $method->invoke($action, $app, $restoredScreenshots);

        $this->assertDatabaseHas('steam_app_screenshots', [
            'steam_app_id' => $app->id,
            'screenshot_id' => 3,
            'path_thumbnail' => 'http://example.com/thumb3_restored.jpg',
            'path_full' => 'http://example.com/full3_restored.jpg',
            'deleted_at' => null,
        ]);
    }

    /**
     * Test that the storeMovies method correctly handles movies.
     */
    public function test_store_movies(): void
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

        // Create an instance of the action via container
        $action = app(FetchSteamAppDetailsAction::class);
        $method = new ReflectionMethod($action, 'storeMovies');
        $method->setAccessible(true);
        $method->invoke($action, $app, $newMovies);

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

        // Re-introduce soft-deleted movie 3 and verify it is restored
        $restoredMovies = [
            [
                'id' => 3,
                'name' => 'Movie 3 Restored',
                'thumbnail' => 'http://example.com/thumb3.jpg',
                'webm' => [
                    '480' => 'http://example.com/webm_480_3.webm',
                    'max' => 'http://example.com/webm_max_3.webm',
                ],
                'mp4' => [
                    '480' => 'http://example.com/mp4_480_3.mp4',
                    'max' => 'http://example.com/mp4_max_3.mp4',
                ],
                'highlight' => true,
            ],
        ];
        $method->invoke($action, $app, $restoredMovies);

        $this->assertDatabaseHas('steam_app_movies', [
            'steam_app_id' => $app->id,
            'movie_id' => 3,
            'name' => 'Movie 3 Restored',
            'deleted_at' => null,
        ]);
    }

    /**
     * Test that the execute method correctly fetches details for a specific app by appid.
     */
    public function test_fetch_steam_app_details_for_specific_app(): void
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
            'shared.akamai.steamstatic.com/*' => Http::response('', 404),
            // Any other request will return a failure response
            '*' => Http::response([
                'success' => false,
            ]),
        ]);

        // Create an instance of the action via container
        $action = app(FetchSteamAppDetailsAction::class);

        // Call the execute method with a specific appid
        $action->execute(123456);

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

    /**
     * Ensure release_date is null when coming_soon is true and date is a placeholder like "Coming soon".
     */
    public function test_release_date_is_null_when_coming_soon_true_with_placeholder(): void
    {
        // Create the target Steam app
        $app = SteamApp::factory()->create([
            'appid' => 123456,
            'name' => 'Coming Soon App',
        ]);

        // Fake Steam API response
        Http::fake([
            'store.steampowered.com/api/appdetails?appids=123456&cc=us&l=en' => Http::response([
                '123456' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Coming Soon App',
                        'steam_appid' => 123456,
                        'required_age' => 0,
                        'is_free' => false,
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                        'release_date' => [
                            'coming_soon' => true,
                            'date' => 'Coming soon',
                        ],
                    ],
                ],
            ]),
            'shared.akamai.steamstatic.com/*' => Http::response('', 404),
        ]);

        // Execute
        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute(123456);

        // Assert DB stored with release_date null and coming_soon true
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $app->id,
            'name' => 'Coming Soon App',
            'type' => 'game',
            'coming_soon' => 1,
        ]);
        // release_date must be null
        $this->assertDatabaseMissing('steam_app_details', [
            'steam_app_id' => $app->id,
            'release_date' => '0000-00-00',
        ]);
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $app->id,
            'release_date' => null,
        ]);
    }

    /**
     * Ensure release_date is null when date is a placeholder (e.g., "TBA") even if coming_soon is false.
     */
    public function test_release_date_is_null_when_placeholder_date_and_not_coming_soon(): void
    {
        // Create the target Steam app
        $app = SteamApp::factory()->create([
            'appid' => 654321,
            'name' => 'TBA App',
        ]);

        // Fake Steam API response with case-insensitive placeholder
        Http::fake([
            'store.steampowered.com/api/appdetails?appids=654321&cc=us&l=en' => Http::response([
                '654321' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'TBA App',
                        'steam_appid' => 654321,
                        'required_age' => 0,
                        'is_free' => false,
                        'platforms' => [
                            'windows' => true,
                            'mac' => true,
                            'linux' => false,
                        ],
                        'release_date' => [
                            'coming_soon' => false,
                            'date' => 'tBa',
                        ],
                    ],
                ],
            ]),
            'shared.akamai.steamstatic.com/*' => Http::response('', 404),
        ]);

        // Execute
        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute(654321);

        // Assert DB stored with release_date null and coming_soon false
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $app->id,
            'name' => 'TBA App',
            'type' => 'game',
            'coming_soon' => 0,
        ]);
        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $app->id,
            'release_date' => null,
        ]);
    }

    public function test_library_hero_image_is_saved_when_url_exists(): void
    {
        $appid = 789012;
        $app = SteamApp::factory()->create([
            'appid' => $appid,
            'name' => 'Hero Image App',
        ]);

        $heroUrl = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$appid}/library_hero.jpg";
        $libraryUrl = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$appid}/library_600x900.jpg";

        Http::fake([
            "store.steampowered.com/api/appdetails?appids={$appid}&cc=us&l=en" => Http::response([
                (string) $appid => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Hero Image App',
                        'steam_appid' => $appid,
                        'required_age' => 0,
                        'is_free' => false,
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                        'release_date' => [
                            'coming_soon' => false,
                            'date' => '2023-02-02',
                        ],
                    ],
                ],
            ]),
            $heroUrl => Http::response('', 200),
            $libraryUrl => Http::response('', 200),
            '*' => Http::response('', 404),
        ]);

        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute((int) $appid);

        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $app->id,
            'library_hero_image' => $heroUrl,
        ]);
    }

    public function test_saves_additional_fields_from_details(): void
    {
        $appid = 111111;
        $app = SteamApp::factory()->create([
            'appid' => $appid,
            'name' => 'Extra Fields App',
        ]);

        $apiUrl = "store.steampowered.com/api/appdetails?appids={$appid}&cc=us&l=en";

        Http::fake([
            $apiUrl => Http::response([
                (string) $appid => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Extra Fields App',
                        'steam_appid' => $appid,
                        'required_age' => 18,
                        'is_free' => false,
                        'controller_support' => 'full',
                        'dlc' => [2214823, 2214822],
                        'drm_notice' => 'Denuvo',
                        'demos' => [
                            ['appid' => 2407990, 'description' => ''],
                        ],
                        'packages' => [778151, 778152],
                        'package_groups' => [
                            [
                                'name' => 'default',
                                'subs' => [
                                    ['packageid' => 778151, 'price_in_cents_with_discount' => 149000],
                                ],
                            ],
                        ],
                        'metacritic' => ['score' => 76, 'url' => 'https://example.com/mc'],
                        'recommendations' => ['total' => 30810],
                        'achievements' => [
                            'total' => 69,
                            'highlighted' => [
                                ['name' => 'A', 'path' => 'https://example.com/a.jpg'],
                            ],
                        ],
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                        'content_descriptors' => ['ids' => [1, 2], 'notes' => 'Violence'],
                        'ratings' => [
                            'esrb' => [
                                'rating' => 'm',
                                'required_age' => '17',
                            ],
                        ],
                        'release_date' => [
                            'coming_soon' => false,
                            'date' => '2023-02-21',
                        ],
                        'support_info' => [
                            'url' => 'mundfish.com',
                            'email' => '',
                        ],
                    ],
                ],
            ]),
            'shared.akamai.steamstatic.com/*' => Http::response('', 404),
        ]);

        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute((int) $appid);

        $detail = SteamAppDetail::where('steam_app_id', $app->id)->firstOrFail();

        $this->assertSame('full', $detail->controller_support);
        $this->assertSame('Denuvo', $detail->drm_notice);

        // DLCs in table
        $this->assertDatabaseHas('steam_app_dlcs', [
            'steam_app_id' => $app->id,
            'dlc_appid' => 2214823,
        ]);
        $this->assertDatabaseHas('steam_app_dlcs', [
            'steam_app_id' => $app->id,
            'dlc_appid' => 2214822,
        ]);

        // Demos table
        $this->assertDatabaseHas('steam_app_demos', [
            'steam_app_id' => $app->id,
            'appid' => 2407990,
            'description' => '',
        ]);

        // Packages table
        $this->assertDatabaseHas('steam_app_packages', [
            'steam_app_id' => $app->id,
            'package_id' => 778151,
        ]);
        $this->assertDatabaseHas('steam_app_packages', [
            'steam_app_id' => $app->id,
            'package_id' => 778152,
        ]);

        // Package groups and subs
        $this->assertDatabaseHas('steam_app_package_groups', [
            'steam_app_id' => $app->id,
            'name' => 'default',
        ]);
        $groupId = SteamAppPackageGroup::where('steam_app_id', $app->id)
            ->where('name', 'default')
            ->value('id');
        $this->assertNotNull($groupId);
        $this->assertDatabaseHas('steam_app_package_group_subs', [
            'steam_app_package_group_id' => $groupId,
            'packageid' => 778151,
            'price_in_cents_with_discount' => 149000,
        ]);

        // Metacritic flattened
        $this->assertSame(76, $detail->metacritic_score);
        $this->assertSame('https://example.com/mc', $detail->metacritic_url);

        // Totals
        $this->assertSame(30810, $detail->recommendations_total);
        $this->assertSame(69, $detail->achievements_total);

        // Highlighted achievements table
        $this->assertDatabaseHas('steam_app_achievements_highlighted', [
            'steam_app_id' => $app->id,
            'name' => 'A',
            'path' => 'https://example.com/a.jpg',
        ]);

        // Content descriptors: notes + ids
        $this->assertSame('Violence', $detail->content_descriptors_notes);
        $this->assertDatabaseHas('steam_app_content_descriptor_ids', [
            'steam_app_id' => $app->id,
            'descriptor_id' => 1,
        ]);
        $this->assertDatabaseHas('steam_app_content_descriptor_ids', [
            'steam_app_id' => $app->id,
            'descriptor_id' => 2,
        ]);

        // Ratings table
        $this->assertDatabaseHas('steam_app_ratings', [
            'steam_app_id' => $app->id,
            'board' => 'esrb',
            'rating' => 'm',
            'required_age' => '17',
        ]);
    }

    public function test_library_hero_image_is_null_when_url_missing(): void
    {
        $appid = 987654;
        $app = SteamApp::factory()->create([
            'appid' => $appid,
            'name' => 'No Hero Image App',
        ]);

        $heroUrl = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$appid}/library_hero.jpg";

        Http::fake([
            "store.steampowered.com/api/appdetails?appids={$appid}&cc=us&l=en" => Http::response([
                (string) $appid => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'No Hero Image App',
                        'steam_appid' => $appid,
                        'required_age' => 0,
                        'is_free' => false,
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                        'release_date' => [
                            'coming_soon' => false,
                            'date' => '2022-12-12',
                        ],
                    ],
                ],
            ]),
            $heroUrl => Http::response('', 404),
            'shared.akamai.steamstatic.com/*' => Http::response('', 404),
        ]);

        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute((int) $appid);

        $this->assertDatabaseHas('steam_app_details', [
            'steam_app_id' => $app->id,
            'library_hero_image' => null,
        ]);
    }

    public function test_execute_handles_success_false_gracefully_and_updates_timestamp(): void
    {
        $appid = 555555;
        $app = SteamApp::factory()->create([
            'appid' => $appid,
            'name' => 'Delisted App',
            'last_details_update' => null,
        ]);

        Http::fake([
            "store.steampowered.com/api/appdetails?appids={$appid}&cc=us&l=en" => Http::response([
                (string) $appid => [
                    'success' => false,
                ],
            ], 200),
        ]);

        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute($appid);

        $app->refresh();
        $this->assertNotNull($app->last_details_update);
        $this->assertDatabaseMissing('steam_app_details', [
            'steam_app_id' => $app->id,
        ]);
    }

    public function test_empty_requirements_array_is_ignored_while_valid_requirements_are_stored(): void
    {
        $appid = 666666;
        $app = SteamApp::factory()->create([
            'appid' => $appid,
            'name' => 'Requirements App',
        ]);

        Http::fake([
            "store.steampowered.com/api/appdetails?appids={$appid}&cc=us&l=en" => Http::response([
                (string) $appid => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Requirements App',
                        'pc_requirements' => [
                            'minimum' => 'Windows 10',
                            'recommended' => 'Windows 11',
                        ],
                        'mac_requirements' => [],
                        'linux_requirements' => [],
                        'price_overview' => [
                            'currency' => 'USD',
                            'initial' => 1999,
                            'final' => 999,
                            'discount_percent' => 50,
                            'initial_formatted' => '$19.99',
                            'final_formatted' => '$9.99',
                        ],
                    ],
                ],
            ], 200),
            'shared.akamai.steamstatic.com/*' => Http::response('', 404),
        ]);

        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute($appid);

        $this->assertDatabaseHas('steam_app_requirements', [
            'steam_app_id' => $app->id,
            'platform' => 'pc',
            'minimum' => 'Windows 10',
            'recommended' => 'Windows 11',
        ]);
        $this->assertDatabaseMissing('steam_app_requirements', [
            'steam_app_id' => $app->id,
            'platform' => 'mac',
        ]);
        $this->assertDatabaseMissing('steam_app_requirements', [
            'steam_app_id' => $app->id,
            'platform' => 'linux',
        ]);
        $this->assertDatabaseHas('steam_app_price_info', [
            'steam_app_id' => $app->id,
            'currency' => 'USD',
            'initial' => 1999,
            'final' => 999,
            'discount_percent' => 50,
        ]);
    }

    public function test_stores_categories_genres_developers_publishers_and_prunes_removed_sub_entities(): void
    {
        $appid = 777777;
        $app = SteamApp::factory()->create([
            'appid' => $appid,
            'name' => 'Full Relations App',
        ]);

        // Pre-seed stale child records that should be pruned on update
        $app->dlcs()->create(['dlc_appid' => 999]);
        $app->demos()->create(['appid' => 888, 'description' => 'Old demo']);
        $app->packages()->create(['package_id' => 777]);
        $oldGroup = $app->packageGroups()->create(['name' => 'old_group']);
        $oldGroup->subs()->create(['packageid' => 666]);
        $app->achievementsHighlighted()->create(['name' => 'OldAch', 'path' => 'old.jpg']);
        $app->contentDescriptorIds()->create(['descriptor_id' => 99]);
        $app->ratings()->create(['board' => 'old_board', 'rating' => 'x']);

        Http::fake([
            "store.steampowered.com/api/appdetails?appids={$appid}&cc=us&l=en" => Http::response([
                (string) $appid => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Full Relations App',
                        'categories' => [
                            ['id' => 1, 'description' => 'Multi-player'],
                            ['id' => 2, 'description' => 'Single-player'],
                        ],
                        'genres' => [
                            ['id' => '1', 'description' => 'Action'],
                            ['id' => '2', 'description' => 'Action'], // duplicate description with different genre_id
                        ],
                        'developers' => ['Valve', 'Hidden Path'],
                        'publishers' => ['Valve'],
                        'dlc' => [1001],
                        'demos' => [['appid' => 2002, 'description' => 'New demo']],
                        'packages' => [3003],
                        'package_groups' => [
                            [
                                'name' => 'default',
                                'subs' => [
                                    ['packageid' => 3003, 'price_in_cents_with_discount' => 999],
                                ],
                            ],
                        ],
                        'achievements' => [
                            'total' => 1,
                            'highlighted' => [
                                ['name' => 'NewAch', 'path' => 'new.jpg'],
                            ],
                        ],
                        'content_descriptors' => ['ids' => [5]],
                        'ratings' => [
                            'pegi' => ['rating' => '18'],
                        ],
                        'release_date' => [
                            'coming_soon' => false,
                            'date' => 'not-a-valid-date-string',
                        ],
                    ],
                ],
            ], 200),
            'shared.akamai.steamstatic.com/*' => Http::sequence()
                ->push('', 405)
                ->push('', 200)
                ->push('', 404),
        ]);

        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute($appid);

        $app->refresh();
        $this->assertCount(2, $app->categories);
        $this->assertCount(2, $app->genres);
        $this->assertCount(2, $app->developers);
        $this->assertCount(1, $app->publishers);
        $this->assertNull($app->detail->release_date);
        $this->assertNotNull($app->detail->library_image);

        // Verify stale records were pruned
        $this->assertDatabaseMissing('steam_app_dlcs', ['steam_app_id' => $app->id, 'dlc_appid' => 999]);
        $this->assertDatabaseMissing('steam_app_demos', ['steam_app_id' => $app->id, 'appid' => 888]);
        $this->assertDatabaseMissing('steam_app_packages', ['steam_app_id' => $app->id, 'package_id' => 777]);
        $this->assertDatabaseMissing('steam_app_package_groups', ['steam_app_id' => $app->id, 'name' => 'old_group']);
        $this->assertDatabaseMissing('steam_app_achievements_highlighted', ['steam_app_id' => $app->id, 'name' => 'OldAch']);
        $this->assertDatabaseMissing('steam_app_content_descriptor_ids', ['steam_app_id' => $app->id, 'descriptor_id' => 99]);
        $this->assertDatabaseMissing('steam_app_ratings', ['steam_app_id' => $app->id, 'board' => 'old_board']);
    }

    public function test_execute_returns_early_for_unknown_app_and_throws_on_api_error(): void
    {
        $action = app(FetchSteamAppDetailsAction::class);

        Http::fake();
        $action->execute(9999999);
        Http::assertNothingSent();

        SteamApp::factory()->create(['appid' => 888888, 'name' => 'Error App']);
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([], 500),
        ]);

        $this->expectException(LaravelSteamAppsDbException::class);
        $action->execute(888888);
    }
}
