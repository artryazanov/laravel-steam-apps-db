<?php

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Artryazanov\LaravelSteamAppsDb\Components\FetchSteamAppNewsComponent;

class FetchSteamAppNewsJob extends FetchSteamAppBasicJob
{
    protected function doJob(): void
    {
        $component = new FetchSteamAppNewsComponent;
        $component->fetchSteamAppNews((string) $this->appid);
    }
}
