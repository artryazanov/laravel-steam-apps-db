<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Jobs;

use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class FetchSteamAppJobsUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->flushCacheAndLocks();
        Bus::fake();
    }

    protected function tearDown(): void
    {
        $this->flushCacheAndLocks();
        parent::tearDown();
    }

    public function test_details_job_is_unique_per_appid(): void
    {
        FetchSteamAppDetailsJob::dispatch(42);
        FetchSteamAppDetailsJob::dispatch(42); // duplicate: should be ignored by ShouldBeUnique

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 1);
    }

    public function test_news_job_is_unique_per_appid(): void
    {
        FetchSteamAppNewsJob::dispatch(42);
        FetchSteamAppNewsJob::dispatch(42); // duplicate: should be ignored by ShouldBeUnique

        Bus::assertDispatchedTimes(FetchSteamAppNewsJob::class, 1);
    }

    public function test_uniqueness_is_scoped_per_job_class(): void
    {
        // Same appid, different job classes should not block each other
        FetchSteamAppDetailsJob::dispatch(42);
        FetchSteamAppNewsJob::dispatch(42);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 1);
        Bus::assertDispatchedTimes(FetchSteamAppNewsJob::class, 1);
    }

    public function test_different_appids_are_not_blocked(): void
    {
        // Different app IDs should result in separate dispatches
        FetchSteamAppDetailsJob::dispatch(42);
        FetchSteamAppDetailsJob::dispatch(43);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 2);
    }

    public function test_dispatch_again_after_lock_clear(): void
    {
        // First dispatch acquires the unique lock
        FetchSteamAppDetailsJob::dispatch(4242);
        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 1);

        // Simulate job completion by clearing unique locks in the ArrayStore
        $this->flushCacheAndLocks();

        // Now dispatch again – should be allowed
        Bus::fake();
        FetchSteamAppDetailsJob::dispatch(4242);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 1);
    }

    /**
     * Flush cache storage and also clear ArrayStore locks used by ShouldBeUnique jobs.
     */
    private function flushCacheAndLocks(): void
    {
        Cache::flush();

        $store = Cache::getStore();
        if (property_exists($store, 'locks')) {
            $store->locks = [];
        }
    }
}

