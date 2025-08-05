<?php

namespace Artryazanov\LaravelSteamAppsDb\Console;

use Illuminate\Console\Command;

class NullCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'null:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'A null command that does nothing but provides a Command instance for components';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // This command does nothing
    }

    /**
     * Write a string as an information output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function info($string, $verbosity = null)
    {
        // Do nothing, suppress output
    }

    /**
     * Write a string as a standard output.
     *
     * @param  string  $string
     * @param  string|null  $style
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function line($string, $style = null, $verbosity = null)
    {
        // Do nothing, suppress output
    }

    /**
     * Write a string as a warning output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function warn($string, $verbosity = null)
    {
        // Do nothing, suppress output
    }

    /**
     * Write a string as an error output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function error($string, $verbosity = null)
    {
        // Do nothing, suppress output
    }
}
