<?php

namespace App\Observers;

use App\Models\Team;
use App\Services\OrganizationContext;

class TeamObserver
{
    /**
     * Handle the Team "creating" event.
     *
     * @param Team $team
     * @return void
     */
    public function creating(Team $team): void
    {
        // If no organization is set and we have a current organization context,
        // set it automatically
        if (!$team->organisation_id && ($orgId = OrganizationContext::getCurrentOrganizationId())) {
            $team->organisation_id = $orgId;
        }
    }
}
