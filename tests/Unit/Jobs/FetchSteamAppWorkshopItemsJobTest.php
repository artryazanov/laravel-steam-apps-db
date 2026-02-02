<?php

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Jobs;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppWorkshopItemsAction;
use Artryazanov\LaravelSteamAppsDb\Actions\ImportSteamAppsAction;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppWorkshopItemsJob;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
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
             ['appid' => 500, 'name' => 'Test Game']
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
             ['appid' => 600, 'name' => 'Test Game 2']
        ]);
        $this->app->instance(SteamApiClient::class, $mockClient);

        $action = app(ImportSteamAppsAction::class);
        $action->execute();

        Queue::assertNotPushed(FetchSteamAppWorkshopItemsJob::class);
    }
}
