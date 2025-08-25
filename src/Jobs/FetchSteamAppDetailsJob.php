<?php

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Artryazanov\LaravelSteamAppsDb\Components\FetchSteamAppDetailsComponent;

class FetchSteamAppDetailsJob extends FetchSteamAppBasicJob
{
    protected function doJob(): void
    {
        $component = new FetchSteamAppDetailsComponent();
        $component->fetchSteamAppDetails((string) $this->appid);
    }
}
