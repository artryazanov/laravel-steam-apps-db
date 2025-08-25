<?php

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

abstract class FetchSteamAppBasicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The Steam application ID.
     */
    public int $appid;

    /**
     * Maximum number of attempts.
     */
    public int $tries = 3;

    /**
     * Backoff in seconds between attempts.
     */
    public int $backoff = 30;

    public function __construct(int $appid)
    {
        $this->appid = $appid;
    }

    public function handle(): void
    {
        $key = 'job:laravel-steam-apps-db-jobs:lock';
        $executed = RateLimiter::attempt($key, 1, function () {$this->doJob();}, 1);
        if (! $executed) {
            $this->release(1);
        }
    }

    abstract protected function doJob(): void;
}
