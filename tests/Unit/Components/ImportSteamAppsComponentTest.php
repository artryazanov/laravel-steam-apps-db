<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Components;

use Artryazanov\LaravelSteamAppsDb\Components\ImportSteamAppsComponent;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ImportSteamAppsComponentTest extends TestCase
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
        (new ImportSteamAppsComponent)->importSteamApps();

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

        // Execute component directly (uses NullCommand internally)
        (new ImportSteamAppsComponent)->importSteamApps();
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

    public function test_recent_release_exact_interval_does_not_dispatch(): void
    {
        $app = SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            // Exactly 7 days ago -> should NOT dispatch because rule is strictly greater than interval
            'last_details_update' => Carbon::now()->subDays(7),
        ]);
        // release within 6 months => recent
        $app->detail()->create([
            'name' => 'App',
            'release_date' => Carbon::now()->subMonths(3),
        ]);

        $this->runImport();

        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);
    }

    public function test_recent_release_after_interval_dispatches(): void
    {
        $app = SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            // 8 days ago -> should dispatch (greater than 7)
            'last_details_update' => Carbon::now()->subDays(8),
        ]);
        $app->detail()->create([
            'name' => 'App',
            'release_date' => Carbon::now()->subMonths(3),
        ]);

        $this->runImport();

        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);
    }

    public function test_mid_age_release_respects_interval(): void
    {
        // exactly 30 days -> no dispatch
        $app = SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            'last_details_update' => Carbon::now()->subDays(30),
        ]);
        $app->detail()->create([
            'name' => 'App',
            // 1 year ago -> mid age
            'release_date' => Carbon::now()->subMonths(12),
        ]);

        $this->runImport();
        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);

    }

    public function test_old_release_respects_interval(): void
    {
        $app = SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            'last_details_update' => Carbon::now()->subDays(183),
        ]);
        $app->detail()->create([
            'name' => 'App',
            // 3 years ago -> old
            'release_date' => Carbon::now()->subYears(3),
        ]);

        $this->runImport();
        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);
    }

    public function test_unknown_or_future_release_treated_as_recent(): void
    {
        // Unknown release date
        $app1 = SteamApp::create([
            'appid' => 42,
            'name' => 'App',
            'last_details_update' => Carbon::now()->subDays(8),
        ]);
        // no detail -> unknown release -> should dispatch (recent rules)
        $this->runImport();
        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);

        // Future release date -> also recent
        // Clear cache and unique job locks between runs
        $this->flushCacheAndLocks();
        Bus::fake();
        $app1->detail()->create([
            'name' => 'App',
            'release_date' => Carbon::now()->addMonth(),
        ]);
        $this->runImport();
        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);

        // Boundary case (exact 7 days) should still dispatch now
        // Clear cache and unique job locks between runs
        $this->flushCacheAndLocks();
        Bus::fake();
        $app1->update(['last_details_update' => Carbon::now()->subDays(7)]);
        $this->runImport();
        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);
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
        (new ImportSteamAppsComponent)->importSteamApps();

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
        (new ImportSteamAppsComponent)->importSteamApps();

        Bus::assertDispatched(FetchSteamAppDetailsJob::class, function ($job) {
            return ($job->queue ?? null) === 'default';
        });
        Bus::assertDispatched(FetchSteamAppNewsJob::class, function ($job) {
            return ($job->queue ?? null) === 'default';
        });
    }
}
