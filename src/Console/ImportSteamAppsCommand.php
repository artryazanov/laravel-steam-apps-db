<?php

namespace Artryazanov\LaravelSteamAppsDb\Console;

use Artryazanov\LaravelSteamAppsDb\Components\ImportSteamAppsComponent;
use Illuminate\Console\Command;

class ImportSteamAppsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:import-apps';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Steam apps from the Steam API and store them in the database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting import of Steam apps...');

        $importComponent = new ImportSteamAppsComponent();
        $importComponent->importSteamApps($this);

        $this->info('Import of Steam apps completed!');
    }
}
