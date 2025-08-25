<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Jobs;

use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Support\Facades\Bus;

class FetchSteamAppJobsUniqueTest extends TestCase
{
    public function test_details_job_prevents_duplicate_for_same_appid(): void
    {
        Bus::fake();

        FetchSteamAppDetailsJob::dispatch(42);
        FetchSteamAppDetailsJob::dispatch(42);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 1);
    }

    public function test_news_job_prevents_duplicate_for_same_appid(): void
    {
        Bus::fake();

        FetchSteamAppNewsJob::dispatch(42);
        FetchSteamAppNewsJob::dispatch(42);

        Bus::assertDispatchedTimes(FetchSteamAppNewsJob::class, 1);
    }

    public function test_different_appids_are_both_dispatched_for_details(): void
    {
        Bus::fake();

        FetchSteamAppDetailsJob::dispatch(1);
        FetchSteamAppDetailsJob::dispatch(2);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 2);
    }

    public function test_details_and_news_with_same_appid_do_not_conflict(): void
    {
        Bus::fake();

        FetchSteamAppDetailsJob::dispatch(7);
        FetchSteamAppNewsJob::dispatch(7);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 1);
        Bus::assertDispatchedTimes(FetchSteamAppNewsJob::class, 1);
    }
}
