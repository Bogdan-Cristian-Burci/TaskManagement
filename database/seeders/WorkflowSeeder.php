<?php

namespace Database\Seeders;

use App\Models\Status;
use App\Models\StatusTransition;
use App\Models\BoardTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class WorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Setting up complete workflow from config/workflow.php...');

        // Get the workflow configuration
        $workflowConfig = config('workflow');

        if (empty($workflowConfig)) {
            $this->command->error('Workflow configuration not found in config/workflow.php');
            return;
        }

        // Begin transaction to ensure atomicity
        DB::beginTransaction();

        try {
            // Step 1: Set up statuses
            $statusMap = $this->setupStatuses($workflowConfig['statuses'] ?? []);

            // Step 2: Set up global transitions
            $this->setupGlobalTransitions($statusMap, $workflowConfig['global_transitions'] ?? []);

            // Step 3: Set up board templates that reference the statuses
            $this->setupBoardTemplates($statusMap, $workflowConfig['board_templates'] ?? []);

            // Commit all changes
            DB::commit();

            $this->command->info('Workflow setup completed successfully.');
        } catch (\Exception $e) {
            // Rollback on any error
            DB::rollBack();
            $this->command->error('Workflow setup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Set up all statuses from config.
     *
     * @param array $statusConfigs
     * @return array Map of status names to IDs
     */
    protected function setupStatuses(array $statusConfigs): array
    {
        $this->command->info('Setting up statuses...');

        // Clear existing statuses
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Status::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Clear status caches
        Cache::forget('statuses:all');
        Cache::forget('statuses:default');

        $now = now();

        // Create all statuses
        $statusEntries = [];
        foreach ($statusConfigs as $config) {
            $statusEntries[] = array_merge($config, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Status::insert($statusEntries);

        // Build name-to-id map
        $statusMap = [];
        foreach (Status::all() as $status) {
            $statusMap[$status->name] = $status->id;
        }

        $this->command->info('Created ' . count($statusEntries) . ' statuses.');

        return $statusMap;
    }

    /**
     * Set up global transitions between statuses.
     *
     * @param array $statusMap
     * @param array $transitionConfigs
     * @return void
     */
    protected function setupGlobalTransitions(array $statusMap, array $transitionConfigs): void
    {
        $this->command->info('Setting up global transitions...');

        // Clear existing global transitions
        StatusTransition::whereNull('board_id')->delete();

        // Clear transition caches
        Cache::forget('status_transitions:all');

        $now = now();
        $transitionEntries = [];

        // Process each transition
        foreach ($transitionConfigs as $config) {
            $fromStatusId = $statusMap[$config['from']] ?? null;
            $toStatusId = $statusMap[$config['to']] ?? null;

            if ($fromStatusId && $toStatusId) {
                $transitionEntries[] = [
                    'name' => $config['name'] ?? "{$config['from']} to {$config['to']}",
                    'from_status_id' => $fromStatusId,
                    'to_status_id' => $toStatusId,
                    'board_id' => null, // Global transition
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } else {
                $this->command->warn("Skipping transition: {$config['from']} to {$config['to']} - Status not found.");
            }
        }

        // Insert all transitions at once
        if (!empty($transitionEntries)) {
            StatusTransition::insert($transitionEntries);
            $this->command->info('Created ' . count($transitionEntries) . ' global transitions.');
        } else {
            $this->command->warn('No global transitions created.');
        }
    }

    /**
     * Set up board templates with columns mapped to statuses.
     *
     * @param array $statusMap
     * @param array $templateConfigs
     * @return void
     */
    protected function setupBoardTemplates(array $statusMap, array $templateConfigs): void
    {
        $this->command->info('Setting up board templates...');

        // Clear the cache for board templates
        BoardTemplate::clearCaches();

        $templatesCount = 0;

        foreach ($templateConfigs as $key => $config) {
            // Process columns to map status names to IDs
            $columnsStructure = [];
            foreach ($config['columns'] as $index => $column) {
                $statusId = $statusMap[$column['status']] ?? null;

                if (!$statusId) {
                    $this->command->warn("Status not found for column: {$column['name']}");
                    continue;
                }

                $columnsStructure[] = [
                    'name' => $column['name'],
                    'color' => $column['color'] ?? '#6C757D',
                    'wip_limit' => $column['wip_limit'] ?? null,
                    'status_id' => $statusId,
                ];
            }

            // Process board-specific transitions
            $boardTransitions = [];
            if (isset($config['board_specific_transitions']) && is_array($config['board_specific_transitions'])) {
                foreach ($config['board_specific_transitions'] as $transition) {
                    $fromStatusId = $statusMap[$transition['from']] ?? null;
                    $toStatusId = $statusMap[$transition['to']] ?? null;

                    if ($fromStatusId && $toStatusId) {
                        $boardTransitions[] = [
                            'from_status_id' => $fromStatusId,
                            'to_status_id' => $toStatusId,
                            'name' => $transition['name'] ?? "{$transition['from']} to {$transition['to']}",
                        ];
                    }
                }
            }

            // Add transitions to the settings
            $settings = $config['settings'] ?? [];
            if (!empty($boardTransitions)) {
                $settings['transitions'] = $boardTransitions;
            }

            // Create or update the board template
            BoardTemplate::withoutGlobalScope('withoutSystem')
                ->withoutGlobalScope('OrganizationScope')
                ->updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $config['name'],
                        'description' => $config['description'] ?? null,
                        'columns_structure' => $columnsStructure,
                        'settings' => $settings,
                        'is_system' => true,
                        'is_active' => true,
                        'organisation_id' => null
                    ]
                );

            $templatesCount++;
        }

        $this->command->info("Created or updated {$templatesCount} board templates.");
    }
}
