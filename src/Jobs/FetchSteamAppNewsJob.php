<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppNewsAction;
use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;

class FetchSteamAppNewsJob extends FetchSteamAppBasicJob
{
    /**
     * @throws LaravelSteamAppsDbException
     */
    protected function doJob(): void
    {
        $action = app(FetchSteamAppNewsAction::class);
        $action->execute((int) $this->appid);
    }
}
