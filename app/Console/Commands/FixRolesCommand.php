<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixRolesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'roles:fix {user_id? : The ID of the user to fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix role assignments for users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            $this->fixUserRoles(User::find($userId));
        } else {
            User::chunk(100, function($users) {
                foreach ($users as $user) {
                    $this->fixUserRoles($user);
                }
            });
        }

        $this->info('Role assignments have been fixed!');
    }

    protected function fixUserRoles(User $user)
    {
        if (!$user->organisation_id) {
            $this->warn("User {$user->id} has no organisation_id, skipping...");
            return;
        }

        $this->info("Fixing roles for user {$user->id} in organisation {$user->organisation_id}");

        // Find admin role for this org
        $adminRole = Role::firstOrCreate(
            [
                'name' => 'admin',
                'guard_name' => 'api',
                'organisation_id' => $user->organisation_id,
            ],
            [
                'level' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Check if role assignment exists
        $exists = DB::table('model_has_roles')
            ->where('role_id', $adminRole->id)
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->where('organisation_id', $user->organisation_id)
            ->exists();

        if (!$exists) {
            $this->info("Adding admin role for user {$user->id}");

            // Insert directly to avoid issues with trait
            DB::table('model_has_roles')->insert([
                'role_id' => $adminRole->id,
                'model_id' => $user->id,
                'model_type' => get_class($user),
                'organisation_id' => $user->organisation_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $this->info("User {$user->id} already has admin role");
        }
    }
}
