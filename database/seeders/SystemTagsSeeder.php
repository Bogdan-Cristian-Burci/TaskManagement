<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemTagsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creating system default tags...');

        // Organisation ID for demo organisation (assumed to be 1)
        $organisationId = 1;

        // Define system tags
        $systemTags = [
            [
                'name' => 'Needs Discussion',
                'color' => '#FF851B',
                'project_id' => null,
                'organisation_id' => $organisationId,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'External Dependency',
                'color' => '#B10DC9',
                'project_id' => null,
                'organisation_id' => $organisationId,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        // Insert the default tags
        foreach ($systemTags as $tagData) {
            // Check if the tag already exists to prevent duplicates
            $existingTag = Tag::where('name', $tagData['name'])
                ->where('organisation_id', $organisationId)
                ->where('is_system', true)
                ->first();

            if (!$existingTag) {
                Tag::create($tagData);
            }
        }

        $this->command->info('System tags created successfully.');
    }
}
