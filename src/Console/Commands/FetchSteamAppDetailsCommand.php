<?php

namespace Artryazanov\LaravelSteamAppsDb\Console\Commands;

use Artryazanov\LaravelSteamAppsDb\Components\FetchSteamAppDetailsComponent;
use Illuminate\Console\Command;

class FetchSteamAppDetailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:fetch-app-details {count=10 : Number of apps to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch detailed information about Steam games and store it in the database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $argCount = $this->argument('count');
        if (!is_numeric($argCount)) {
            $this->error("Count has wrong value: $argCount!");
            return;
        }

        $limit = (int) $argCount;
        $this->info('Starting fetch of Steam app details...');

        $fetchComponent = new FetchSteamAppDetailsComponent();
        $fetchComponent->fetchSteamAppDetails($this, $limit);

        $this->info('Fetch of Steam app details completed!');
    }

}
