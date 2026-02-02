<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Console;

use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class ImportSteamAppsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Enable news scanning for these console tests unless specifically disabled
        config(['laravel-steam-apps-db.enable_news_scanning' => true]);
    }

    public function test_command_imports_steam_apps_successfully(): void
    {
        // Mock HTTP response from Steam API
        Http::fake([
            'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' => Http::response([
                'applist' => [
                    'apps' => [
                        ['appid' => 1, 'name' => 'Test App 1'],
                        ['appid' => 2, 'name' => 'Test App 2'],
                        ['appid' => 3, 'name' => 'Test App 3'],
                    ],
                ],
            ], 200),
        ]);

        // Fake bus to capture dispatched jobs
        Bus::fake();

        // Run the command
        $this->artisan('steam:import-apps')
            ->expectsOutputToContain('Starting import of Steam apps...')
            ->expectsOutputToContain('Fetching data from Steam API...')
            ->expectsOutputToContain('Found 3 apps in the Steam API response')
            ->expectsOutputToContain('Processing apps in chunks of 500')
            ->expectsOutputToContain('Processing chunk 1 of 1')
            ->expectsOutputToContain('Processed 3 of 3 apps')
            ->expectsOutputToContain('Import completed: 3 apps created, 0 apps updated')
            ->expectsOutputToContain('Import of Steam apps completed!')
            ->assertSuccessful();

        // Assert jobs were dispatched for each app
        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 3);
        Bus::assertDispatchedTimes(FetchSteamAppNewsJob::class, 3);

        // Assert that the apps were created in the database
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 1,
            'name' => 'Test App 1',
        ]);
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 2,
            'name' => 'Test App 2',
        ]);
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 3,
            'name' => 'Test App 3',
        ]);

        // Verify HTTP request was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' &&
                $request->method() === 'GET';
        });
    }

    public function test_command_updates_existing_steam_apps(): void
    {
        // Create existing steam apps
        SteamApp::create([
            'appid' => 1,
            'name' => 'Old App Name 1',
        ]);
        SteamApp::create([
            'appid' => 2,
            'name' => 'Old App Name 2',
        ]);

        // Mock HTTP response from Steam API
        Http::fake([
            'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' => Http::response([
                'applist' => [
                    'apps' => [
                        ['appid' => 1, 'name' => 'Updated App Name 1'],
                        ['appid' => 2, 'name' => 'Updated App Name 2'],
                        ['appid' => 3, 'name' => 'New App 3'],
                    ],
                ],
            ], 200),
        ]);

        // Fake bus to capture dispatched jobs
        Bus::fake();

        // Run the command
        $this->artisan('steam:import-apps')
            ->expectsOutputToContain('Import completed: 1 apps created, 2 apps updated')
            ->assertSuccessful();

        // Assert jobs were dispatched for each app (3 total)
        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 3);
        Bus::assertDispatchedTimes(FetchSteamAppNewsJob::class, 3);

        // Assert that the apps were updated in the database
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 1,
            'name' => 'Updated App Name 1',
        ]);
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 2,
            'name' => 'Updated App Name 2',
        ]);
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 3,
            'name' => 'New App 3',
        ]);
    }

    public function test_command_handles_api_errors_gracefully(): void
    {
        // Mock HTTP response from Steam API with an error
        Http::fake([
            'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' => Http::response([
                'error' => 'API rate limit exceeded',
            ], 429),
        ]);

        // Fake bus to capture dispatched jobs
        Bus::fake();

        // Run the command
        $this->artisan('steam:import-apps')
            ->expectsOutputToContain('Starting import of Steam apps...')
            ->expectsOutputToContain('Fetching data from Steam API...')
            ->expectsOutputToContain('Failed to fetch data from Steam API: 429')
            ->expectsOutputToContain('Import of Steam apps completed!')
            ->assertSuccessful();

        // No jobs should be dispatched on API error
        Bus::assertNothingDispatched();

        // Assert that no apps were created in the database
        $this->assertDatabaseCount('steam_apps', 0);

        // Verify HTTP request was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' &&
                $request->method() === 'GET';
        });
    }

    public function test_command_handles_invalid_response_format_gracefully(): void
    {
        // Mock HTTP response from Steam API with invalid format
        Http::fake([
            'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' => Http::response([
                'invalid' => 'response format',
            ], 200),
        ]);

        // Fake bus to capture dispatched jobs
        Bus::fake();

        // Run the command
        $this->artisan('steam:import-apps')
            ->expectsOutputToContain('Starting import of Steam apps...')
            ->expectsOutputToContain('Fetching data from Steam API...')
            ->expectsOutputToContain('Invalid response format from Steam API')
            ->expectsOutputToContain('Import of Steam apps completed!')
            ->assertSuccessful();

        // No jobs should be dispatched on invalid response
        Bus::assertNothingDispatched();

        // Assert that no apps were created in the database
        $this->assertDatabaseCount('steam_apps', 0);

        // Verify HTTP request was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' &&
                $request->method() === 'GET';
        });
    }

    public function test_command_handles_exceptions_gracefully(): void
    {
        // Create a mock that throws an exception
        Http::fake(function () {
            throw new \Exception('Test exception');
        });

        // Fake bus to capture dispatched jobs
        Bus::fake();

        // Run the command
        $this->artisan('steam:import-apps')
            ->expectsOutputToContain('Starting import of Steam apps...')
            ->expectsOutputToContain('An error occurred during import: Test exception')
            ->expectsOutputToContain('Import of Steam apps completed!')
            ->assertSuccessful();

        // No jobs should be dispatched on exception
        Bus::assertNothingDispatched();

        // Assert that no apps were created in the database
        $this->assertDatabaseCount('steam_apps', 0);
    }

    public function test_command_skips_apps_with_empty_names(): void
    {
        // Mock HTTP response from Steam API with some apps having empty names
        Http::fake([
            'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' => Http::response([
                'applist' => [
                    'apps' => [
                        ['appid' => 1, 'name' => 'Test App 1'],
                        ['appid' => 2, 'name' => ''],  // Empty name
                        ['appid' => 3, 'name' => 'Test App 3'],
                        ['appid' => 4, 'name' => null], // Null name
                    ],
                ],
            ], 200),
        ]);

        // Fake bus to capture dispatched jobs
        Bus::fake();

        // Run the command
        $this->artisan('steam:import-apps')
            ->expectsOutputToContain('Found 4 apps in the Steam API response')
            ->expectsOutputToContain('Processed 2 of 4 apps')  // Only 2 processed because 2 have empty names
            ->expectsOutputToContain('Import completed: 2 apps created, 0 apps updated')
            ->assertSuccessful();

        // Assert jobs were dispatched only for valid apps (2)
        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 2);
        Bus::assertDispatchedTimes(FetchSteamAppNewsJob::class, 2);

        // Assert that only apps with non-empty names were created
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 1,
            'name' => 'Test App 1',
        ]);
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 3,
            'name' => 'Test App 3',
        ]);
        $this->assertDatabaseCount('steam_apps', 2);
    }
}
