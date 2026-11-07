<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Tests\Unit\Actions;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppDetailsAction;
use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppNewsAction;
use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppWorkshopItemsAction;
use Artryazanov\LaravelSteamAppsDb\Actions\UpdateSteamAppsByTitleAction;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppWorkshopItemsJob;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;

class UpdateSteamAppsByTitleActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->flushCacheAndLocks();
    }

    protected function tearDown(): void
    {
        $this->flushCacheAndLocks();
        parent::tearDown();
    }

    private function flushCacheAndLocks(): void
    {
        Cache::flush();

        $store = Cache::getStore();
        if (property_exists($store, 'locks')) {
            $store->locks = [];
        }
    }

    public function test_partial_match_in_local_db_dispatches_jobs_for_matching_apps(): void
    {
        config([
            'laravel-steam-apps-db.enable_news_scanning' => true,
            'laravel-steam-apps-db.enable_workshop_scanning' => true,
            'laravel-steam-apps-db.queue' => 'targeted',
        ]);

        SteamApp::create(['appid' => 292030, 'name' => 'The Witcher 3: Wild Hunt']);
        SteamApp::create(['appid' => 20920, 'name' => 'The Witcher 2: Assassins of Kings Enhanced Edition']);
        SteamApp::create(['appid' => 570, 'name' => 'Dota 2']);

        Bus::fake();

        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute('witcher');

        $this->assertCount(2, $matched);
        $this->assertEqualsCanonicalizing([292030, 20920], $matched->pluck('appid')->all());

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 2);
        Bus::assertDispatchedTimes(FetchSteamAppNewsJob::class, 2);
        Bus::assertDispatchedTimes(FetchSteamAppWorkshopItemsJob::class, 2);

        Bus::assertDispatched(FetchSteamAppDetailsJob::class, function ($job) {
            return in_array($job->appid, [292030, 20920], true) && $job->queue === 'targeted';
        });
    }

    public function test_exact_match_in_local_db_only_matches_exact_title_case_insensitively(): void
    {
        config([
            'laravel-steam-apps-db.enable_news_scanning' => false,
            'laravel-steam-apps-db.enable_workshop_scanning' => false,
        ]);

        SteamApp::create(['appid' => 570, 'name' => 'Dota 2']);
        SteamApp::create(['appid' => 205790, 'name' => 'Dota 2 Test']);

        Bus::fake();

        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute('dOtA 2', exact: true);

        $this->assertCount(1, $matched);
        $this->assertSame(570, $matched->first()->appid);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 1);
        Bus::assertNotDispatched(FetchSteamAppNewsJob::class);
        Bus::assertNotDispatched(FetchSteamAppWorkshopItemsJob::class);
    }

    public function test_special_wildcard_characters_in_title_are_escaped(): void
    {
        SteamApp::create(['appid' => 1001, 'name' => '100% Orange Juice']);
        SteamApp::create(['appid' => 1002, 'name' => '1000 Amps']);

        Bus::fake();

        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute('100%');

        $this->assertCount(1, $matched);
        $this->assertSame(1001, $matched->first()->appid);
    }

    public function test_sync_mode_executes_actions_immediately_without_queue(): void
    {
        config([
            'laravel-steam-apps-db.enable_news_scanning' => true,
            'laravel-steam-apps-db.enable_workshop_scanning' => true,
        ]);

        SteamApp::create(['appid' => 570, 'name' => 'Dota 2']);

        Bus::fake();

        $detailsAction = Mockery::mock(FetchSteamAppDetailsAction::class);
        $detailsAction->shouldReceive('execute')->once()->with(570);

        $newsAction = Mockery::mock(FetchSteamAppNewsAction::class);
        $newsAction->shouldReceive('execute')->once()->with(570);

        $workshopAction = Mockery::mock(FetchSteamAppWorkshopItemsAction::class);
        $workshopAction->shouldReceive('execute')->once()->with(570, '*')->andReturn('cursor_2');
        $workshopAction->shouldReceive('execute')->once()->with(570, 'cursor_2')->andReturn(null);

        $this->app->instance(FetchSteamAppDetailsAction::class, $detailsAction);
        $this->app->instance(FetchSteamAppNewsAction::class, $newsAction);
        $this->app->instance(FetchSteamAppWorkshopItemsAction::class, $workshopAction);

        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute('Dota 2', exact: true, sync: true);

        $this->assertCount(1, $matched);
        Bus::assertNothingDispatched();
    }

    public function test_from_api_mode_imports_and_dispatches_for_matching_apps_from_steam_api(): void
    {
        config(['laravel-steam-apps-db.enable_news_scanning' => false]);

        Http::fake([
            'https://store.steampowered.com/api/storesearch/*' => Http::response([
                'total' => 3,
                'items' => [
                    ['id' => 730, 'name' => 'Counter-Strike 2'],
                    ['id' => 10, 'name' => 'Counter-Strike'],
                    ['id' => 570, 'name' => 'Dota 2'],
                ],
            ], 200),
        ]);

        Bus::fake();

        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute('Counter-Strike 2', exact: true, sync: false, fromApi: true);

        $this->assertCount(1, $matched);
        $this->assertDatabaseHas('steam_apps', [
            'appid' => 730,
            'name' => 'Counter-Strike 2',
        ]);
        $this->assertDatabaseMissing('steam_apps', [
            'appid' => 570,
        ]);

        Bus::assertDispatchedTimes(FetchSteamAppDetailsJob::class, 1);
    }

    public function test_empty_title_returns_empty_collection_and_dispatches_nothing(): void
    {
        SteamApp::create(['appid' => 570, 'name' => 'Dota 2']);

        Bus::fake();

        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute('   ');

        $this->assertCount(0, $matched);
        Bus::assertNothingDispatched();
    }

    public function test_from_api_partial_match_skips_empty_names_and_handles_update_errors(): void
    {
        config([
            'laravel-steam-apps-db.enable_news_scanning' => false,
            'laravel-steam-apps-db.enable_workshop_scanning' => false,
        ]);

        Http::fake([
            'https://store.steampowered.com/api/storesearch/*' => Http::response([
                'total' => 3,
                'items' => [
                    ['id' => 1, 'name' => ''],
                    ['id' => 70, 'name' => 'Half-Life'],
                    ['id' => 220, 'name' => 'Half-Life 2'],
                ],
            ], 200),
        ]);

        $detailsAction = Mockery::mock(FetchSteamAppDetailsAction::class);
        $detailsAction->shouldReceive('execute')->once()->with(70)->andThrow(new \Exception('Sync failure'));
        $detailsAction->shouldReceive('execute')->once()->with(220);
        $this->app->instance(FetchSteamAppDetailsAction::class, $detailsAction);

        $errors = [];
        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute(
            title: 'Half-Life',
            exact: false,
            sync: true,
            fromApi: true,
            errorCallback: function (string $msg) use (&$errors) {
                $errors[] = $msg;
            },
        );

        $this->assertCount(2, $matched);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Failed to update appid 70: Sync failure', $errors[0]);
    }

    public function test_from_api_handles_steam_api_exceptions_gracefully(): void
    {
        Http::fake([
            'https://store.steampowered.com/api/storesearch/*' => Http::response([], 500),
        ]);

        $errors = [];
        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute(
            title: 'Dota 2',
            fromApi: true,
            errorCallback: function (string $msg) use (&$errors) {
                $errors[] = $msg;
            },
        );

        $this->assertCount(0, $matched);
        $this->assertNotEmpty($errors);
    }

    public function test_handles_unexpected_generic_exception_gracefully(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Unexpected connection failure');
        });

        $errors = [];
        $action = app(UpdateSteamAppsByTitleAction::class);
        $matched = $action->execute(
            title: 'Dota 2',
            fromApi: true,
            errorCallback: function (string $msg) use (&$errors) {
                $errors[] = $msg;
            },
        );

        $this->assertCount(0, $matched);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('An error occurred during targeted update: Unexpected connection failure', $errors[0]);
    }
}
