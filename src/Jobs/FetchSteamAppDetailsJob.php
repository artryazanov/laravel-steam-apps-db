<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppDetailsAction;
use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;
use Artryazanov\LaravelSteamAppsDb\Services\SteamApiClient;

class FetchSteamAppDetailsJob extends FetchSteamAppBasicJob
{
    /**
     * @throws LaravelSteamAppsDbException
     */
    protected function doJob(): void
    {
        // In a real app we might want to use method injection in handle(),
        // but since this extends a base job that likely calls doJob() from handle(),
        // we can instantiate the action here or use app() container.
        // Using app() container resolves dependencies (SteamApiClient).
        $action = app(FetchSteamAppDetailsAction::class);
        $action->execute((int) $this->appid);
    }
}
