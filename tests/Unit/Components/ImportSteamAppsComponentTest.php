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
            'laravel-steam-apps-db.release_age_thresholds.recent_months' => 6,
            'laravel-steam-apps-db.release_age_thresholds.mid_max_years' => 2,
            'laravel-steam-apps-db.details_update_intervals.recent_days' => 7,
            'laravel-steam-apps-db.details_update_intervals.mid_days' => 30,
            'laravel-steam-apps-db.details_update_intervals.old_days' => 183,
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
        (new ImportSteamAppsComponent())->importSteamApps();

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
        (new ImportSteamAppsComponent())->importSteamApps();
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

        Bus::assertNotDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertNotDispatched(FetchSteamAppNewsJob::class);
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
        Bus::assertNotDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertNotDispatched(FetchSteamAppNewsJob::class);

        // 31 days -> dispatch
        Bus::fake();
        $app->update(['last_details_update' => Carbon::now()->subDays(31)]);
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
        Bus::assertNotDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertNotDispatched(FetchSteamAppNewsJob::class);

        // 184 days -> dispatch
        Bus::fake();
        $app->update(['last_details_update' => Carbon::now()->subDays(184)]);
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
        Cache::flush();
        Bus::fake();
        $app1->detail()->create([
            'name' => 'App',
            'release_date' => Carbon::now()->addMonth(),
        ]);
        $this->runImport();
        Bus::assertDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertDispatched(FetchSteamAppNewsJob::class);

        // But at the boundary (exact 7 days) should NOT dispatch for recent
        Cache::flush();
        Bus::fake();
        $app1->update(['last_details_update' => Carbon::now()->subDays(7)]);
        $this->runImport();
        Bus::assertNotDispatched(FetchSteamAppDetailsJob::class);
        Bus::assertNotDispatched(FetchSteamAppNewsJob::class);
    }
}
