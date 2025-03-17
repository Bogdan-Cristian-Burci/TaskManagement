<?php

namespace Database\Seeders;

use App\Models\Organisation;
use Illuminate\Database\Seeder;

class DemoOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo organization if it doesn't exist
        Organisation::firstOrCreate(
            ['name' => 'Demo Organization'],
            [
                'name' => 'Demo Organization',
                'description' => 'Standard organization for templates and demo purposes',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }
}
