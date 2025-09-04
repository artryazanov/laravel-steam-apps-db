<?php

namespace Artryazanov\LaravelSteamAppsDb\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $startedAt = microtime(true);
        try {
            $this->doJob();
        } catch (Exception $e) {
            $this->fail($e);
        }

        $decaySeconds = (int) config('laravel-steam-apps-db.decay_seconds');
        if ($decaySeconds > 0) {
            $elapsedMicros = (int) ((microtime(true) - $startedAt) * 1_000_000);
            $sleepMicros = max(0, ($decaySeconds * 1_000_000) - $elapsedMicros);
            if ($sleepMicros > 0) {
                usleep($sleepMicros);
            }
        }
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
