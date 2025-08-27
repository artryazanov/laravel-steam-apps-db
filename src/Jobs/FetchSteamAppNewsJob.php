<?php

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Artryazanov\LaravelSteamAppsDb\Components\FetchSteamAppNewsComponent;
use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;

class FetchSteamAppNewsJob extends FetchSteamAppBasicJob
{
    /**
     * @throws LaravelSteamAppsDbException
     */
    protected function doJob(): void
    {
        $component = new FetchSteamAppNewsComponent;
        $component->fetchSteamAppNews((string) $this->appid);
    }
}
