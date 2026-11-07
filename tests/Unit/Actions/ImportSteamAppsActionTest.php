<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Actions;

use Artryazanov\LaravelSteamAppsDb\Actions\ImportSteamAppsAction;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppWorkshopItemsJob;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ImportSteamAppsActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic config for tests
        config([
            // Enable news scanning for default test expectations
            'laravel-steam-apps-db.enable_news_scanning' => true,
        ]);

        // Fix "now" for deterministic date math
        Carbon::setTestNow(Carbon::create(2025, 8, 27, 12, 0, 0, 'UTC'));
    }

    public function test_news_scanning_can_be_disabled_by_config(): void
    {
        // Disable news scanning
        config(['laravel-steam-apps-db.enable_news_scanning' => false]);

        // Pre-create app with null last_details_update to force dispatch
        SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            'last_details_update' => null,
        ]);

        Bus::fake();
        $this->httpFakeSingleApp();

        $action = new ImportSteamAppsAction(new SteamApiClient);
        $action->execute();

        // Details should still be dispatched
        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        // News should NOT be dispatched when disabled
        Bus::assertNotDispatched(FetchSteamAppNewsJob::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function httpFakeSingleApp(int $appid = 42, string $name = 'App'): void
    {
        Http::fake([
            'https://api.steampowered.com/ISteamApps/GetAppList/v2/?format=json' => Http::response([
                'applist' => [
                    'apps' => [
                        ['appid' => $appid, 'name' => $name],
                    ],
                ],
            ], 200),
        ]);
    }

    private function runImport(): void
    {
        Bus::fake();
        $this->httpFakeSingleApp();

        // Execute action directly
        $action = new ImportSteamAppsAction(new SteamApiClient);
        $action->execute();
    }

    public function test_dispatch_when_last_update_is_null(): void
    {
        // Pre-create app with null last_details_update
        SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            'last_details_update' => null,
        ]);

        $this->runImport();

        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);
    }

    public function test_always_dispatches_regardless_of_recent_last_update_or_release_age(): void
    {
        $app = SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            'last_details_update' => Carbon::now()->subDays(1),
        ]);
        $app->detail()->create([
            'name' => 'App',
            'release_date' => Carbon::now()->subYears(5),
        ]);

        $this->runImport();

        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);
    }

    public function test_invalid_chunk_size_is_clamped_to_at_least_one(): void
    {
        $this->flushCacheAndLocks();
        config(['laravel-steam-apps-db.chunk_size' => 0]);

        $this->runImport();

        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        $this->assertDatabaseHas('steam_apps', ['appid' => 42, 'name' => 'App']);
    }

    /**
     * Flush cache storage and also clear ArrayStore locks used by ShouldBeUnique jobs.
     */
    private function flushCacheAndLocks(): void
    {
        Cache::flush();

        $store = Cache::getStore();
        // In tests we typically use ArrayStore where locks are kept separately
        if (property_exists($store, 'locks')) {
            $store->locks = [];
        }
    }

    public function test_jobs_are_dispatched_to_configured_queue(): void
    {
        // Ensure clean unique locks
        $this->flushCacheAndLocks();

        // Configure custom queue
        config([
            'laravel-steam-apps-db.queue' => 'high',
            'laravel-steam-apps-db.enable_news_scanning' => true,
        ]);

        // Force dispatch by having null last_details_update
        SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            'last_details_update' => null,
        ]);

        Bus::fake();
        $this->httpFakeSingleApp();

        $action = new ImportSteamAppsAction(new SteamApiClient);
        $action->execute();

        // Both jobs should be on the configured queue
        Bus::assertDispatched(FetchSteamAppDetailsJob::class, function ($job) {
            return ($job->queue ?? null) === 'high';
        });
        Bus::assertDispatched(FetchSteamAppNewsJob::class, function ($job) {
            return ($job->queue ?? null) === 'high';
        });
    }

    public function test_default_queue_is_used_when_not_overridden(): void
    {
        // Ensure clean unique locks
        $this->flushCacheAndLocks();

        // Do not set the queue config; it should default to 'default'
        SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            'last_details_update' => null,
        ]);

        Bus::fake();
        $this->httpFakeSingleApp();

        $action = new ImportSteamAppsAction(new SteamApiClient);
        $action->execute();

        Bus::assertDispatched(FetchSteamAppDetailsJob::class, function ($job) {
            return ($job->queue ?? null) === 'default';
        });
        Bus::assertDispatched(FetchSteamAppNewsJob::class, function ($job) {
            return ($job->queue ?? null) === 'default';
        });
    }

    public function test_title_filter_with_partial_match_skips_empty_names(): void
    {
        $this->flushCacheAndLocks();

        Http::fake([
            'https://store.steampowered.com/api/storesearch/*' => Http::response([
                'total' => 4,
                'items' => [
                    ['id' => 1, 'name' => ''],
                    ['id' => 70, 'name' => 'Half-Life'],
                    ['id' => 220, 'name' => 'Half-Life 2'],
                    ['id' => 570, 'name' => 'Dota 2'],
                ],
            ], 200),
        ]);

        Bus::fake();

        $action = new ImportSteamAppsAction(new SteamApiClient);
        $action->execute(titleFilter: 'half-life', exactMatch: false);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 2);
        $this->assertDatabaseCount('steam_apps', 2);
    }

    public function test_workshop_scanning_dispatches_workshop_job_when_enabled(): void
    {
        $this->flushCacheAndLocks();
        config([
            'laravel-steam-apps-db.enable_news_scanning' => false,
            'laravel-steam-apps-db.enable_workshop_scanning' => true,
        ]);

        Bus::fake();
        $this->httpFakeSingleApp(4000, "Garry's Mod");

        $action = new ImportSteamAppsAction(new SteamApiClient);
        $action->execute();

        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertNotDispatched(FetchSteamAppNewsJob::class);
        Bus::assertDispatched(FetchSteamAppWorkshopItemsJob::class);
    }

    public function test_handles_job_dispatch_exception_via_error_callback(): void
    {
        $this->flushCacheAndLocks();
        $this->httpFakeSingleApp(42, 'App');

        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('Queue unavailable'));

        $errors = [];
        $action = new ImportSteamAppsAction(new SteamApiClient);
        $action->execute(
            errorCallback: function (string $msg) use (&$errors) {
                $errors[] = $msg;
            }
        );

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Failed to dispatch jobs for appid 42: Queue unavailable', $errors[0]);
    }
}
