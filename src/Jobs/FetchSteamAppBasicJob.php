<?php

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

abstract class FetchSteamAppBasicJob implements ShouldBeUnique, ShouldQueue
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

    public function uniqueId(): string
    {
        return static::class.':'.$this->appid;
    }

    public function handle(): void
    {
        $key = 'job:laravel-steam-apps-db-jobs:lock';
        $decaySeconds = (int) config('laravel-steam-apps-db.decay_seconds');
        $executed = RateLimiter::attempt($key, 1, function () {
            try {
                $this->doJob();
            } catch (Exception $e) {
                $this->fail($e);
            }
        }, $decaySeconds);
        if (! $executed) {
            $this->release($decaySeconds);
        }
    }

    abstract protected function doJob(): void;
}
