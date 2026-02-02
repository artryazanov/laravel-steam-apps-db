<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Artryazanov\LaravelSteamAppsDb\Actions\FetchSteamAppWorkshopItemsAction;

class FetchSteamAppWorkshopItemsJob extends FetchSteamAppBasicJob
{
    public string $cursor;

    public function __construct(int $appid, string $cursor = '*')
    {
        parent::__construct($appid);
        $this->cursor = $cursor;
    }

    protected function doJob(): void
    {
        $action = app(FetchSteamAppWorkshopItemsAction::class);
        
        // Fetch one page
        $nextCursor = $action->execute($this->appid, $this->cursor);

        // If there is a next page, schedule next job
        if ($nextCursor && $nextCursor !== $this->cursor) {
            self::dispatch($this->appid, $nextCursor)
                ->onQueue(config('laravel-steam-apps-db.queue', 'default'));
        }
    }
}
