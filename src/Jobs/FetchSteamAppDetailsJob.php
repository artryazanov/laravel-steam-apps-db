<?php

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Artryazanov\LaravelSteamAppsDb\Components\FetchSteamAppDetailsComponent;
use Artryazanov\LaravelSteamAppsDb\Exceptions\LaravelSteamAppsDbException;

class FetchSteamAppDetailsJob extends FetchSteamAppBasicJob
{
    /**
     * @throws LaravelSteamAppsDbException
     */
    protected function doJob(): void
    {
        $component = new FetchSteamAppDetailsComponent;
        $component->fetchSteamAppDetails((string) $this->appid);
    }
}
