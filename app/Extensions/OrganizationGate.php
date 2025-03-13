<?php

namespace App\Extensions;

use App\Models\Organisation;
use Illuminate\Support\Facades\Gate;

class OrganizationGate
{
    /**
     * Register all organization-aware gates
     */
    public static function register()
    {
        Gate::before(function ($user, $ability, $arguments) {
            // Skip if no user or no arguments
            if (!$user || empty($arguments)) {
                return null;
            }

            // Only intercept permission checks with organization context
            $lastArg = end($arguments);
            if (!($lastArg instanceof Organisation || is_numeric($lastArg))) {
                return null;
            }

            // Get organization object or ID
            $organisation = $lastArg;

            try {
                // Check permission with organization context
                return $user->hasOrganisationPermission($ability, $organisation);
            } catch (\Exception $e) {
                \Log::error("Error in organization permission check: " . $e->getMessage());
                return false;
            }
        });
    }
}
