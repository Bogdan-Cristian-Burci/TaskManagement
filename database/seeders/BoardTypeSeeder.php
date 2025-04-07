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
    public function run()
    {
        $this->command->info('Creating board types from templates...');

        // Get all active board templates
        $templates = BoardTemplate::where('is_active', true)
            ->get();

        foreach ($templates as $template) {
            BoardType::create([
                'template_id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
            ]);
        }

        $this->command->info('Created ' . $templates->count() . ' board types.');
    }
}
