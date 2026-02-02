<?php

declare(strict_types=1);

namespace Artryazanov\LaravelSteamAppsDb\Console;

use Artryazanov\LaravelSteamAppsDb\Actions\ImportSteamAppsAction;
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
     */
    protected $description = 'Import Steam apps from the Steam API and store them in the database';

    public function __construct(
        protected ImportSteamAppsAction $importSteamAppsAction
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting import of Steam apps...');

        $this->importSteamAppsAction->execute(
            infoCallback: fn (string $msg) => $this->info($msg),
            errorCallback: fn (string $msg) => $this->error($msg),
        );

        $this->info('Import of Steam apps completed!');
    }
}
