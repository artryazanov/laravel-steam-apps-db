<?php

namespace Artryazanov\LaravelSteamAppsDb\Console\Commands;

use Artryazanov\LaravelSteamAppsDb\Components\FetchSteamAppNewsComponent;
use Illuminate\Console\Command;

class FetchSteamAppNewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:fetch-app-news {count=10 : Number of apps to process} {--appid= : Steam application ID to fetch news for a specific app}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch latest news for Steam apps and store them in the database';

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
        $appid = $this->option('appid');

        $this->info('Starting fetch of Steam app news...');

        $fetchComponent = new FetchSteamAppNewsComponent();
        $fetchComponent->fetchSteamAppNews($this, $limit, $appid);

        $this->info('Fetch of Steam app news completed!');
    }
}
