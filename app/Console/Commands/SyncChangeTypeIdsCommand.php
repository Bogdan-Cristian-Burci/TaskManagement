<?php

namespace App\Console\Commands;

use App\Models\ChangeType;
use App\Models\TaskHistory;
use Illuminate\Console\Command;

class SyncChangeTypeIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-change-type-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync change_type_id in task histories based on field_changed values';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to sync change_type_id values...');

        $changeTypes = ChangeType::all(['id', 'name']);
        $total = 0;

        $this->output->progressStart(count($changeTypes));

        foreach ($changeTypes as $changeType) {
            $count = TaskHistory::where('field_changed', $changeType->name)
                ->whereNull('change_type_id')
                ->update(['change_type_id' => $changeType->id]);

            $total += $count;
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->info("Sync completed! Updated {$total} task history records.");

        return Command::SUCCESS;
    }
}
