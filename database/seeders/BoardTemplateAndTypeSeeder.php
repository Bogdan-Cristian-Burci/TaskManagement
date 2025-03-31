<?php

namespace Database\Seeders;

use App\Models\BoardTemplate;
use App\Models\BoardType;
use Illuminate\Database\Seeder;

class BoardTemplateAndTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Syncing board templates and types...');

        // Get templates from config
        $templatesConfig = config('board_templates');
        $templatesCount = 0;
        $typesCount = 0;

        foreach ($templatesConfig as $key => $templateData) {
            // Skip non-array config items
            if (!is_array($templateData)) {
                continue;
            }

            // Create or update template
            $template = BoardTemplate::withoutGlobalScope('withoutSystem')
                ->withoutGlobalScope('OrganizationScope')
                ->updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $templateData['name'],
                        'description' => $templateData['description'] ?? null,
                        'columns_structure' => $templateData['columns_structure'],
                        'settings' => $templateData['settings'] ?? [],
                        'is_system' => true,
                        'is_active' => true,
                        'organisation_id' => null
                    ]
                );

            $templatesCount++;

            // Create board type for this template if it doesn't exist
            $boardType = BoardType::firstOrCreate(
                ['template_id' => $template->id],
                [
                    'name' => $template->name,
                    'description' => $template->description ?? ('Default board type for ' . $template->name)
                ]
            );

            if ($boardType->wasRecentlyCreated) {
                $typesCount++;
            }
        }

        $this->command->info("Completed: $templatesCount templates and $typesCount new board types created.");
    }
}
