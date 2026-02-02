<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Actions;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppDetailsAction;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Models\SteamAppMovie;
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

        $detail = \Artryazanov\LaravelSteamAppsDb\Models\SteamAppDetail::where('steam_app_id', $app->id)->firstOrFail();

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
        $groupId = \Artryazanov\LaravelSteamAppsDb\Models\SteamAppPackageGroup::where('steam_app_id', $app->id)
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
}
