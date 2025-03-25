<?php

namespace App\Console\Commands;

use App\Models\BoardTemplate;
use Illuminate\Console\Command;

class SyncBoardTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'board:sync-templates {--force : Force overwrite of existing modified system templates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize board templates from configuration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Syncing board templates from config...');

        $force = $this->option('force');
        if ($force) {
            $this->warn('Force mode is enabled. This will overwrite any modified system templates.');
        }

        try {
            BoardTemplate::syncFromConfig();
            $this->info('Board templates synchronized successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error synchronizing board templates: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
