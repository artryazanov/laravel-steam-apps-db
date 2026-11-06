<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Jobs;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppDetailsAction;
use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppNewsAction;
use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppWorkshopItemsAction;
use Artryazanov\LaravelSteamAppsDb\Actions\ImportSteamAppsAction;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppWorkshopItemsJob;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Redis\Limiters\DurationLimiterBuilder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;

class FetchSteamAppWorkshopItemsJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('laravel-steam-apps-db.decay_seconds', 0);
    }

    public function test_job_executes_action_and_dispatches_next_job_if_cursor_exists()
    {
        Queue::fake();

        // 1. Mock Action
        $mockAction = Mockery::mock(FetchSteamAppWorkshopItemsAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(100, '*')
            ->andReturn('next_cursor_xyz');

        $this->app->instance(FetchSteamAppWorkshopItemsAction::class, $mockAction);

        // 2. Dispatch Job
        (new FetchSteamAppWorkshopItemsJob(100, '*'))->handle();

        // 3. Assert Next Job Dispatched
        Queue::assertPushed(FetchSteamAppWorkshopItemsJob::class, function ($job) {
            return $job->appid === 100 && $job->cursor === 'next_cursor_xyz';
        });
    }

    public function test_job_stops_recursion_if_no_cursor()
    {
        Queue::fake();

        $mockAction = Mockery::mock(FetchSteamAppWorkshopItemsAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(200, '*')
            ->andReturn(null);

        $this->app->instance(FetchSteamAppWorkshopItemsAction::class, $mockAction);

        (new FetchSteamAppWorkshopItemsJob(200, '*'))->handle();

        Queue::assertNotPushed(FetchSteamAppWorkshopItemsJob::class);
    }

    public function test_import_action_dispatches_workshop_job_when_enabled()
    {
        Queue::fake();
        Config::set('laravel-steam-apps-db.enable_workshop_scanning', true);

        // Mock SteamApiClient for ImportAction
        $mockClient = Mockery::mock(SteamApiClient::class);
        $mockClient->shouldReceive('getAppList')->once()->andReturn([
            ['appid' => 500, 'name' => 'Test Game'],
        ]);
        $this->app->instance(SteamApiClient::class, $mockClient);

        // Execute Import
        $action = app(ImportSteamAppsAction::class);
        $action->execute();

        // Assert Job Pushed
        Queue::assertPushed(FetchSteamAppWorkshopItemsJob::class, function ($job) {
            return $job->appid === 500;
        });
    }

    public function test_import_action_does_not_dispatch_workshop_job_when_disabled()
    {
        Queue::fake();
        Config::set('laravel-steam-apps-db.enable_workshop_scanning', false);

        $mockClient = Mockery::mock(SteamApiClient::class);
        $mockClient->shouldReceive('getAppList')->once()->andReturn([
            ['appid' => 600, 'name' => 'Test Game 2'],
        ]);
        $this->app->instance(SteamApiClient::class, $mockClient);

        $action = app(ImportSteamAppsAction::class);
        $action->execute();

        Queue::assertNotPushed(FetchSteamAppWorkshopItemsJob::class);
    }

    public function test_basic_job_configures_retry_until_and_max_exceptions(): void
    {
        $job = new FetchSteamAppWorkshopItemsJob(100, '*');

        $this->assertSame(3, $job->maxExceptions);
        $this->assertTrue($job->retryUntil() > now());
    }

    public function test_details_and_news_jobs_execute_their_actions(): void
    {
        $detailsAction = Mockery::mock(FetchSteamAppDetailsAction::class);
        $detailsAction->shouldReceive('execute')->once()->with(570);
        $this->app->instance(FetchSteamAppDetailsAction::class, $detailsAction);

        $newsAction = Mockery::mock(FetchSteamAppNewsAction::class);
        $newsAction->shouldReceive('execute')->once()->with(570);
        $this->app->instance(FetchSteamAppNewsAction::class, $newsAction);

        (new FetchSteamAppDetailsJob(570))->handle();
        (new FetchSteamAppNewsJob(570))->handle();
    }

    public function test_basic_job_marks_failed_when_do_job_throws_without_throttling(): void
    {
        $detailsAction = Mockery::mock(FetchSteamAppDetailsAction::class);
        $detailsAction->shouldReceive('execute')->once()->with(570)->andThrow(new Exception('API failure'));
        $this->app->instance(FetchSteamAppDetailsAction::class, $detailsAction);

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('fail')->once()->with(Mockery::type(Exception::class));

        $job = new FetchSteamAppDetailsJob(570);
        $job->setJob($queueJob);
        $job->handle();
    }

    public function test_basic_job_throttling_allows_execution_and_handles_exceptions(): void
    {
        Config::set('laravel-steam-apps-db.decay_seconds', 1);

        $limiter = Mockery::mock(DurationLimiterBuilder::class);
        $limiter->shouldReceive('allow')->with(1)->andReturnSelf();
        $limiter->shouldReceive('every')->with(1)->andReturnSelf();
        $limiter->shouldReceive('then')->once()->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        Redis::shouldReceive('throttle')
            ->once()
            ->with('laravel-steam-apps-db:throttle')
            ->andReturn($limiter);

        $detailsAction = Mockery::mock(FetchSteamAppDetailsAction::class);
        $detailsAction->shouldReceive('execute')->once()->with(570)->andThrow(new Exception('Throttled failure'));
        $this->app->instance(FetchSteamAppDetailsAction::class, $detailsAction);

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('fail')->once()->with(Mockery::type(Exception::class));

        $job = new FetchSteamAppDetailsJob(570);
        $job->setJob($queueJob);
        $job->handle();
    }

    public function test_basic_job_throttling_releases_job_when_rate_limited(): void
    {
        Config::set('laravel-steam-apps-db.decay_seconds', 1);

        $limiter = Mockery::mock(DurationLimiterBuilder::class);
        $limiter->shouldReceive('allow')->with(1)->andReturnSelf();
        $limiter->shouldReceive('every')->with(1)->andReturnSelf();
        $limiter->shouldReceive('then')->once()->andReturnUsing(function (callable $callback, callable $failure) {
            return $failure();
        });

        Redis::shouldReceive('throttle')
            ->once()
            ->with('laravel-steam-apps-db:throttle')
            ->andReturn($limiter);

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('release')->once()->with(0);

        $job = new FetchSteamAppDetailsJob(570);
        $job->setJob($queueJob);
        $job->handle();
    }
}
