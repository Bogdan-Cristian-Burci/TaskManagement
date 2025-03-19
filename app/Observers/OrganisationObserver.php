<?php

namespace App\Observers;

use App\Models\Organisation;
use App\Services\RoleTemplateService;

class OrganisationObserver
{
    /**
     * Handle the Organisation "created" event.
     *
     * @param Organisation $organisation
     * @return void
     */
    public function created(Organisation $organisation): void
    {
        // Create default roles for the new organization
        $roleTemplateService = new RoleTemplateService();
        $roleTemplateService->applySystemTemplatesToOrganisation($organisation);
    }
}
