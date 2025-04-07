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

        try {
            // Step 1: Set up statuses
            $statusMap = $this->setupStatuses($workflowConfig['statuses'] ?? []);

            // Step 2: Process global transitions (now just returns the data)
            $globalTransitions = $this->setupGlobalTransitions($statusMap, $workflowConfig['global_transitions'] ?? []);

            // Step 3: Set up board templates with both global and template-specific transitions
            $this->setupBoardTemplates($statusMap, $workflowConfig['board_templates'] ?? [], $globalTransitions);

            $this->command->info('Workflow setup completed successfully.');
        } catch (\Exception $e) {
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
     * @return array
     */
    protected function setupGlobalTransitions(array $statusMap, array $transitionConfigs): array
    {
        $this->command->info('Setting up global transitions...');

        // Instead of creating a global template, we'll prepare transition data
        // that will be applied to each board template
        $globalTransitions = [];

        // Process each transition
        foreach ($transitionConfigs as $config) {
            $fromStatusId = $statusMap[$config['from']] ?? null;
            $toStatusId = $statusMap[$config['to']] ?? null;

            if ($fromStatusId && $toStatusId) {
                $globalTransitions[] = [
                    'name' => $config['name'] ?? "{$config['from']} to {$config['to']}",
                    'from_status_id' => $fromStatusId,
                    'to_status_id' => $toStatusId,
                ];
            } else {
                $this->command->warn("Skipping transition: {$config['from']} to {$config['to']} - Status not found.");
            }
        }

        $this->command->info('Created ' . count($globalTransitions) . ' global transitions definition.');

        return $globalTransitions;
    }

    /**
     * Set up transitions at the template level.
     *
     * @param array $statusMap Map of status names to IDs
     * @param array $templateConfigs Board template configurations
     * @param array $globalTransitions Global transitions to apply to each template
     * @return void
     * @throws \Throwable
     */
    protected function setupBoardTemplates(array $statusMap, array $templateConfigs, array $globalTransitions): void
    {
        $this->command->info('Setting up board templates and their transitions...');

        // Begin a transaction for this step
        DB::beginTransaction();

        try {
            // Clear the cache for board templates
            BoardTemplate::clearCaches();

            $templatesCount = 0;
            $transitionsCount = 0;

            // Get category map for category-based transitions
            $categoryMap = [];
            foreach (Status::all() as $status) {
                if (!isset($categoryMap[$status->category])) {
                    $categoryMap[$status->category] = [];
                }
                $categoryMap[$status->category][] = $status->id;
            }

            foreach ($templateConfigs as $key => $config) {
                // Process columns to map status names to IDs
                $columnsStructure = [];
                $statusIdsByColumn = []; // Store which status ID is used by which column

                foreach ($config['columns'] as $index => $column) {
                    // Get status ID from the nested structure
                    $statusName = $column['status']['name'] ?? null;
                    $statusId = $statusMap[$statusName] ?? null;

                    if (!$statusId) {
                        // If the specific status doesn't exist, we need to create it
                        $statusCategory = $column['status']['category'] ?? 'to_do';

                        $newStatus = Status::create([
                            'name' => $statusName,
                            'description' => "Auto-created for {$config['name']} template",
                            'color' => $column['color'] ?? '#6C757D',
                            'icon' => 'circle',
                            'is_default' => false,
                            'position' => 100 + $index, // Give it a high position number
                            'category' => $statusCategory,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $statusId = $newStatus->id;
                        $statusMap[$statusName] = $statusId;

                        // Add to category map
                        if (!isset($categoryMap[$statusCategory])) {
                            $categoryMap[$statusCategory] = [];
                        }
                        $categoryMap[$statusCategory][] = $statusId;

                        $this->command->info("Created new status '{$statusName}' with ID {$statusId}");
                    }

                    $columnsStructure[] = [
                        'name' => $column['name'],
                        'color' => $column['color'] ?? '#6C757D',
                        'wip_limit' => $column['wip_limit'] ?? null,
                        'status_id' => $statusId,
                    ];

                    $statusIdsByColumn[$column['name']] = $statusId;
                }

                // Create or update the board template
                $template = BoardTemplate::withoutGlobalScope('withoutSystem')
                    ->withoutGlobalScope('OrganizationScope')
                    ->updateOrCreate(
                        ['key' => $key],
                        [
                            'name' => $config['name'],
                            'description' => $config['description'] ?? null,
                            'columns_structure' => $columnsStructure,
                            'settings' => $config['settings'] ?? [],
                            'is_system' => true,
                            'is_active' => true,
                            'organisation_id' => null
                        ]
                    );

                $templatesCount++;

                // Now create transitions for this template

                // First, delete existing transitions for this template
                StatusTransition::where('board_template_id', $template->id)->delete();

                // Apply global transitions to this template
                foreach ($globalTransitions as $transition) {
                    $created = $this->createTransitionIfNotExists(
                        $transition['from_status_id'],
                        $transition['to_status_id'],
                        $template->id,
                        $transition['name']
                    );

                    if ($created) {
                        $transitionsCount++;
                    }
                }

                // Create category-based transitions for this template
                foreach ($this->getCategoryTransitions() as $catTransition) {
                    $fromStatusIds = $categoryMap[$catTransition['from_category']] ?? [];
                    $toStatusIds = $categoryMap[$catTransition['to_category']] ?? [];

                    foreach ($fromStatusIds as $fromId) {
                        foreach ($toStatusIds as $toId) {
                            if ($fromId !== $toId) {
                                $created = $this->createTransitionIfNotExists(
                                    $fromId,
                                    $toId,
                                    $template->id,
                                    $catTransition['name']
                                );

                                if ($created) {
                                    $transitionsCount++;
                                }
                            }
                        }
                    }
                }

                // Now add board-specific transitions if defined
                if (isset($config['board_specific_transitions']) && is_array($config['board_specific_transitions'])) {
                    foreach ($config['board_specific_transitions'] as $transition) {
                        // Handle direct status name transitions
                        if (isset($transition['from']) && isset($transition['to'])) {
                            $fromStatusId = $statusMap[$transition['from']] ?? null;
                            $toStatusId = $statusMap[$transition['to']] ?? null;

                            if ($fromStatusId && $toStatusId) {
                                $created = $this->createTransitionIfNotExists(
                                    $fromStatusId,
                                    $toStatusId,
                                    $template->id,
                                    $transition['name'] ?? "{$transition['from']} to {$transition['to']}"
                                );

                                if ($created) {
                                    $transitionsCount++;
                                }
                            }
                        }
                        // Handle column name transitions (which map to statuses)
                        else if (isset($transition['from_column']) && isset($transition['to_column'])) {
                            $fromStatusId = $statusIdsByColumn[$transition['from_column']] ?? null;
                            $toStatusId = $statusIdsByColumn[$transition['to_column']] ?? null;

                            if ($fromStatusId && $toStatusId) {
                                $created = $this->createTransitionIfNotExists(
                                    $fromStatusId,
                                    $toStatusId,
                                    $template->id,
                                    $transition['name'] ?? "{$transition['from_column']} to {$transition['to_column']}"
                                );

                                if ($created) {
                                    $transitionsCount++;
                                }
                            }
                        }
                        // Handle category-based transitions
                        else if (isset($transition['from_category']) && isset($transition['to_category'])) {
                            $fromStatusIds = $categoryMap[$transition['from_category']] ?? [];
                            $toStatusIds = $categoryMap[$transition['to_category']] ?? [];

                            foreach ($fromStatusIds as $fromId) {
                                foreach ($toStatusIds as $toId) {
                                    if ($fromId !== $toId) {
                                        $created = $this->createTransitionIfNotExists(
                                            $fromId,
                                            $toId,
                                            $template->id,
                                            $transition['name'] ?? "{$transition['from_category']} to {$transition['to_category']}"
                                        );

                                        if ($created) {
                                            $transitionsCount++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $this->command->info("Created or updated {$templatesCount} board templates with {$transitionsCount} transitions.");
            // Commit this transaction
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Board template setup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the base category transitions that all templates should have
     *
     * @return array
     */
    protected function getCategoryTransitions(): array
    {
        return [
            ['from_category' => 'todo', 'to_category' => 'in_progress', 'name' => 'Start Work'],
            ['from_category' => 'in_progress', 'to_category' => 'todo', 'name' => 'Move Back to To Do'],
            ['from_category' => 'in_progress', 'to_category' => 'in_progress', 'name' => 'Continue Work'],
            ['from_category' => 'in_progress', 'to_category' => 'done', 'name' => 'Complete Work'],
            ['from_category' => 'done', 'to_category' => 'in_progress', 'name' => 'Reopen'],
            ['from_category' => 'todo', 'to_category' => 'canceled', 'name' => 'Cancel Task'],
            ['from_category' => 'in_progress', 'to_category' => 'canceled', 'name' => 'Cancel Work'],
            ['from_category' => 'done', 'to_category' => 'canceled', 'name' => 'Withdraw Completion'],
        ];
    }

    /**
     * Helper function to create a transition if it doesn't already exist
     *
     * @param int $fromStatusId
     * @param int $toStatusId
     * @param int $boardTemplateId
     * @param string $name
     * @return bool Whether the transition was created
     */
    protected function createTransitionIfNotExists(
        int $fromStatusId,
        int $toStatusId,
        int $boardTemplateId,
        string $name
    ): bool {
        // Check if the transition already exists
        $exists = StatusTransition::where('from_status_id', $fromStatusId)
            ->where('to_status_id', $toStatusId)
            ->where('board_template_id', $boardTemplateId)
            ->exists();

        if (!$exists) {
            StatusTransition::create([
                'name' => $name,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatusId,
                'board_template_id' => $boardTemplateId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        }

        return false;
    }
}
