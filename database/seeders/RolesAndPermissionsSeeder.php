<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\User;
use App\Services\RoleManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Starting permission and role seeding...');

        // Use the RoleManager service
        $roleManager = new RoleManager();

        // 1. Sync all permissions from config
        $permCount = $roleManager->syncAllPermissions();
        $this->command->info("Synced {$permCount} permissions from configuration");

        // 2. Sync all role templates from config
        $templateCount = $roleManager->syncAllRoleTemplates();
        $this->command->info("Synced {$templateCount} role templates from configuration");

        // 3. Create or get the Demo User
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Admin',
                'email' => 'demo@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now()
            ]
        );

        // 4. Create or get the Demo Organization with all required fields
        $demoOrg = Organisation::firstOrCreate(
            ['name' => 'Demo Organization'],
            [
                'name' => 'Demo Organization',
                'unique_id' => 'demo-' . Str::random(8),  // Generate a unique ID
                'description' => 'Standard organization for templates and demo purposes',
                'owner_id' => $demoUser->id,
                'created_by' => $demoUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // 5. Update the demo user's organization_id
        if ($demoUser->organisation_id !== $demoOrg->id) {
            $demoUser->update(['organisation_id' => $demoOrg->id]);
        }
        $this->command->info('Demo Organization created with ID: ' . $demoOrg->id);

        // 6. Create roles for the demo org
        $roleCount = $roleManager->createOrganizationRoles($demoOrg->id);
        $this->command->info("Created {$roleCount} roles for demo organization");

        // 7. Assign admin role to demo user
        $roleManager->assignRoleToUser($demoUser, 'admin', $demoOrg->id);
        $this->command->info('Admin role assigned to demo user');

        $this->command->info('Permissions, templates and roles seeded successfully!');
    }

    /**
     * Create organization-specific roles for all organizations
     */
    public function createOrganizationRoles(): void
    {
        $roleManager = new RoleManager();
        $count = $roleManager->syncOrganizationRoles();
        $this->command->info("Created roles for {$count} organizations");
    }

    /**
     * Ensure an admin user has the required permissions
     *
     * @param int $userId
     * @param int $organisationId
     * @return bool
     */
    public static function addMissingPermissionsToAdmin(int $userId, int $organisationId): bool
    {
        $roleManager = new RoleManager();
        return $roleManager->assignRoleToUser(User::find($userId), 'admin', $organisationId);
    }
}
