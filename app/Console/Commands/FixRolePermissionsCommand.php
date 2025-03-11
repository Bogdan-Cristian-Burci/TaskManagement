<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Organisation;
use Database\Seeders\RolesAndPermissionsSeeder;

class FixRolePermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:fix {--user_id= : The specific user ID to fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix role permissions for admin users';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Fixing role permissions...');

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Get the specific user ID if provided
        $userId = $this->option('user_id');

        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return Command::FAILURE;
            }

            $this->info("Fixing permissions for user {$user->name} (ID: {$userId})");
            $result = RolesAndPermissionsSeeder::addMissingPermissionsToAdmin($userId, $user->organisation_id);

            if ($result) {
                $this->info("Successfully fixed permissions for user {$user->name}");
            } else {
                $this->error("Failed to fix permissions for user {$user->name}");
            }
        } else {
            // Fix for all organizations and admin users
            $organizations = Organisation::all();

            $this->info("Found {$organizations->count()} organizations");

            foreach ($organizations as $organization) {
                $this->info("Processing organization: {$organization->name} (ID: {$organization->id})");

                // Find admin role for this organization
                $adminRole = Role::where('name', 'admin')
                    ->where('organisation_id', $organization->id)
                    ->first();

                if (!$adminRole) {
                    $this->warn("No admin role found for organization {$organization->name}. Skipping.");
                    continue;
                }

                // Find all users with admin role
                $adminUserIds = \DB::table('model_has_roles')
                    ->where('role_id', $adminRole->id)
                    ->where('organisation_id', $organization->id)
                    ->pluck('model_id');

                $this->info("Found {$adminUserIds->count()} admin users for organization {$organization->name}");

                foreach ($adminUserIds as $adminUserId) {
                    $result = RolesAndPermissionsSeeder::addMissingPermissionsToAdmin($adminUserId, $organization->id);
                    if ($result) {
                        $this->info("Fixed permissions for user ID {$adminUserId}");
                    } else {
                        $this->error("Failed to fix permissions for user ID {$adminUserId}");
                    }
                }
            }
        }

        $this->info('Permission fixing completed!');
        return Command::SUCCESS;
    }
}
