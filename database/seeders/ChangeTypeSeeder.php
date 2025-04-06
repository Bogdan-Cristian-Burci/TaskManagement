<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChangeType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Enums\ChangeTypeEnum;

class ChangeTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing change types
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ChangeType::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Clear any cached change types
        Cache::forget('change_types:all');

        $changeTypes = [];

        // Use the config file to get the descriptions
        foreach (config('change_types.types') as $typeName => $typeInfo) {
            $changeTypes[] = [
                'name' => $typeName,
                'description' => $typeInfo['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Use insert instead of create for better performance with multiple records
        ChangeType::insert($changeTypes);

        $this->command->info('ChangeType table seeded successfully!');
    }
}
