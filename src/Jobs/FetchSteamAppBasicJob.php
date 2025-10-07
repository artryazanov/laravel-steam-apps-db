<?php

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

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

    public function handle(): void
    {
        $decaySeconds = (int) config('laravel-steam-apps-db.decay_seconds');

        // If throttling is disabled or misconfigured, run immediately
        if ($decaySeconds <= 0) {
            try {
                $this->doJob();
            } catch (Exception $e) {
                $this->fail($e);
            }
            return;
        }

        // Global rate limit across workers via Redis throttle
        Redis::throttle('laravel-steam-apps-db:throttle')
            ->allow(1)
            ->every($decaySeconds)
            ->then(function () {
                try {
                    $this->doJob();
                } catch (Exception $e) {
                    $this->fail($e);
                }
            }, function () use ($decaySeconds) {
                $sleepMicros = $decaySeconds * 1_000_000;
                if ($sleepMicros > 0) {
                    usleep($sleepMicros);
                }
                // If the rate limit is exceeded, release the job back to the queue
                return $this->release();
            });
    }

    /**
     * Provide a stable unique identifier so duplicate jobs aren't queued.
     */
    public function uniqueId(): string
    {
        return json_encode(['appid' => $this->appid], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    abstract protected function doJob(): void;
}
