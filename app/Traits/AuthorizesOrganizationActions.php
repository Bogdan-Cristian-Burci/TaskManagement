<?php

namespace App\Traits;

use App\Models\Organisation;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

trait AuthorizesOrganizationActions
{
    /**
     * Get the authorization service instance
     */
    protected function authService(): AuthorizationService
    {
        return App::make(AuthorizationService::class);
    }

    /**
     * Check if the current user has a specific permission in the provided organization
     */
    protected function authorizeOrganizationPermission(string $permission, Organisation $organisation, string $message = null)
    {
        $user = auth()->user();

        if (!$user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated');
        }

        if (!$this->authService()->hasOrganisationPermission($user, $permission, $organisation)) {
            abort(Response::HTTP_FORBIDDEN, $message ?? "You don't have the required permission in this organization");
        }
    }

    /**
     * Check if the current user has sufficient role level in the organization
     */
    protected function authorizeOrganizationRoleLevel(int $requiredLevel, Organisation $organisation)
    {
        $user = auth()->user();

        if (!$user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated');
        }

        if (!$this->authService()->hasOrganisationRoleLevel($user, $requiredLevel, $organisation)) {
            abort(Response::HTTP_FORBIDDEN, "You don't have a sufficient role level in this organization");
        }
    }

    /**
     * Get organization from request using different possible sources
     */
    protected function getOrganisationFromRequest(Request $request): ?Organisation
    {
        // Already resolved model instance from route
        if ($request->route('organisation') instanceof Organisation) {
            return $request->route('organisation');
        }

        // Organisation ID from route parameter
        if ($request->route('organisation')) {
            return Organisation::find($request->route('organisation'));
        }

        // Organisation ID from request input
        if ($request->has('organisation_id')) {
            return Organisation::find($request->input('organisation_id'));
        }

        // If we have a project, get its organization
        if ($request->has('project_id')) {
            $project = \App\Models\Project::find($request->input('project_id'));
            if ($project && $project->organisation) {
                return $project->organisation;
            }
        }

        return null;
    }
}
