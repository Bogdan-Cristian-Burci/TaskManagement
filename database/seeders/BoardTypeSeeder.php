<?php

namespace Database\Seeders;

use App\Models\BoardTemplate;
use App\Models\BoardType;
use Illuminate\Database\Seeder;

class BoardTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Creating board types from templates...');

        $templates = BoardTemplate::withoutGlobalScope('withoutSystem')
            ->withoutGlobalScope('OrganizationScope')
            ->where('is_system', true)
            ->get();

        $count = 0;

        foreach ($templates as $template) {
            $boardType = BoardType::firstOrCreate(
                ['template_id' => $template->id],
                [
                    'name' => $template->name,
                    'description' => $template->description ?? ('Default board type for ' . $template->name),
                    'is_active' => true
                ]
            );

            if ($boardType->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->command->info("Created {$count} new board types.");
    }
}
