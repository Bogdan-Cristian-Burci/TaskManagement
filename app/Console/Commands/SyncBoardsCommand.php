<?php

namespace App\Console\Commands;

use Database\Seeders\BoardTemplateAndTypeSeeder;
use Illuminate\Console\Command;

class SyncBoardsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'board:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize board templates and types from configuration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Running board template and type seeder...');

        $seeder = new BoardTemplateAndTypeSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        return Command::SUCCESS;
    }
}
