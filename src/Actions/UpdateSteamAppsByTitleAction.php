<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Actions;

use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppDetailsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppNewsJob;
use Artryazanov\LaravelSteamAppsDb\Jobs\FetchSteamAppWorkshopItemsJob;
use Artryazanov\LaravelSteamAppsDb\Models\SteamApp;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class UpdateSteamAppsByTitleAction
{
    public function __construct(
        protected SteamApiClient $steamApiClient,
        protected FetchSteamAppDetailsAction $fetchDetailsAction,
        protected FetchSteamAppNewsAction $fetchNewsAction,
        protected FetchSteamAppWorkshopItemsAction $fetchWorkshopItemsAction,
    ) {}

    /**
     * Find Steam apps matching the given title and trigger their update (via queue or synchronously).
     *
     * @param  string  $title  Title or substring to search for
     * @param  bool  $exact  Whether to require an exact (case-insensitive) title match
     * @param  bool  $sync  Whether to execute updates synchronously instead of dispatching queued jobs
     * @param  bool  $fromApi  Whether to fetch and import matching apps from Steam Store Search API before updating
     * @param  callable(string): void|null  $infoCallback
     * @param  callable(string): void|null  $errorCallback
     * @return Collection<int, SteamApp>
     */
    public function execute(
        string $title,
        bool $exact = false,
        bool $sync = false,
        bool $fromApi = false,
        ?callable $infoCallback = null,
        ?callable $errorCallback = null,
    ): Collection {
        $infoCallback ??= fn (string $msg) => Log::info($msg);
        $errorCallback ??= fn (string $msg) => Log::error($msg);

        $trimmedTitle = trim($title);
        if ($trimmedTitle === '') {
            return new Collection;
        }

        try {
            if ($fromApi) {
                $apps = $this->findAndImportFromApi($trimmedTitle, $exact, $infoCallback);
            } else {
                /** @var Collection<int, SteamApp> $apps */
                $apps = SteamApp::query()->whereTitle($trimmedTitle, $exact)->get();
            }

            $infoCallback('Found '.$apps->count()." matching Steam apps for title '{$trimmedTitle}'");

            $queue = (string) config('laravel-steam-apps-db.queue', 'default');
            $enableNewsScanning = (bool) config('laravel-steam-apps-db.enable_news_scanning', false);
            $enableWorkshopScanning = (bool) config('laravel-steam-apps-db.enable_workshop_scanning', false);

            foreach ($apps as $steamApp) {
                try {
                    if ($sync) {
                        $this->runSyncUpdate($steamApp, $enableNewsScanning, $enableWorkshopScanning);
                    } else {
                        $this->dispatchUpdateJobs($steamApp, $queue, $enableNewsScanning, $enableWorkshopScanning);
                    }
                } catch (Exception $e) {
                    $errorCallback("Failed to update appid {$steamApp->appid}: {$e->getMessage()}");
                }
            }

            return $apps;
        } catch (LaravelSteamAppsDbException $e) {
            $errorCallback($e->getMessage());
            report($e);

            return new Collection;
        } catch (Exception $e) {
            $errorMessage = 'An error occurred during targeted update: '.$e->getMessage();
            $errorCallback($errorMessage);
            report(new LaravelSteamAppsDbException($errorMessage, $e->getCode(), $e));

            return new Collection;
        }
    }

    /**
     * Search apps by title in Steam Store Search API, filter by title, and upsert matching records.
     *
     * @param  callable(string): void  $infoCallback
     * @return Collection<int, SteamApp>
     *
     * @throws LaravelSteamAppsDbException
     */
    private function findAndImportFromApi(string $title, bool $exact, callable $infoCallback): Collection
    {
        $infoCallback('Fetching data from Steam API...');

        $rawApps = $this->steamApiClient->searchAppsByTitle($title);
        $normalizedTarget = mb_strtolower($title);
        $matchedModels = new Collection;

        foreach ($rawApps as $app) {
            $appName = isset($app['name']) ? trim((string) $app['name']) : '';
            if ($appName === '') {
                continue;
            }

            $isMatch = $exact
                ? mb_strtolower($appName) === $normalizedTarget
                : mb_stripos($appName, $title) !== false;

            if (! $isMatch) {
                continue;
            }

            $steamApp = SteamApp::updateOrCreate(
                ['appid' => $app['appid']],
                ['name' => $app['name']]
            );

            $matchedModels->push($steamApp);
        }

        return $matchedModels;
    }

    /**
     * Dispatch queued update jobs for a single SteamApp.
     */
    private function dispatchUpdateJobs(
        SteamApp $steamApp,
        string $queue,
        bool $enableNewsScanning,
        bool $enableWorkshopScanning,
    ): void {
        FetchSteamAppDetailsJob::dispatch((int) $steamApp->appid)->onQueue($queue);

        if ($enableNewsScanning) {
            FetchSteamAppNewsJob::dispatch((int) $steamApp->appid)->onQueue($queue);
        }

        if ($enableWorkshopScanning) {
            FetchSteamAppWorkshopItemsJob::dispatch((int) $steamApp->appid)->onQueue($queue);
        }
    }

    /**
     * Execute update actions synchronously for a single SteamApp.
     *
     * @throws LaravelSteamAppsDbException
     */
    private function runSyncUpdate(
        SteamApp $steamApp,
        bool $enableNewsScanning,
        bool $enableWorkshopScanning,
    ): void {
        $appid = (int) $steamApp->appid;

        $this->fetchDetailsAction->execute($appid);

        if ($enableNewsScanning) {
            $this->fetchNewsAction->execute($appid);
        }

        if ($enableWorkshopScanning) {
            $cursor = '*';
            do {
                $nextCursor = $this->fetchWorkshopItemsAction->execute($appid, $cursor);
                if (! $nextCursor || $nextCursor === $cursor) {
                    break;
                }
                $cursor = $nextCursor;
            } while (true);
        }
    }
}
