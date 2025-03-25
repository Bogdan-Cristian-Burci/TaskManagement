<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class OrganizationContextMiddleware
{
    /**
     * Handle an incoming request.
     * Sets the organization context for the current request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Try to determine organization ID from various sources
        $orgId = $this->getOrganisationIdFromRequest($request);

        if (Auth::check()) {
            $user = Auth::user();

            if ($orgId) {
                // Verify user belongs to this organization before setting it
                if ($user->organisations()->where('organisations.id', $orgId)->exists()) {

                    // Set in the organization context service
                    OrganizationContext::setCurrentOrganization($orgId);
                    // Set organization on user object
                    $user->organisation_id = $orgId;

                    // We don't save the user model here to avoid unnecessary DB writes
                    // The organization_id is just for the current request

                    // Store in session for future requests
                    Session::put('active_organisation_id', $orgId);
                } else {
                    // If user doesn't belong to requested org, don't set it
                    $orgId = null;
                }
            }

            // If no valid org set and user has no org_id, use their first organization
            if (!$orgId && !$user->organisation_id) {
                $firstOrg = $user->organisations()->first();

                if ($firstOrg) {
                    $orgId = $firstOrg->id;
                    // Set in context service
                    OrganizationContext::setCurrentOrganization($orgId);

                    $user->organisation_id = $firstOrg->id;
                    Session::put('active_organisation_id', $firstOrg->id);
                }
            } else if (!$orgId && $user->organisation_id) {
                // If no org ID from request but user has a default, use that
                $orgId = $user->organisation_id;
                OrganizationContext::setCurrentOrganization($orgId);
            }
        }


        $response = $next($request);

        // Clear context after request is processed
        OrganizationContext::clear();

        return $response;
    }

    /**
     * Get organization ID from various request sources
     *
     * @param Request $request
     * @return int|null
     */
    protected function getOrganisationIdFromRequest(Request $request): ?int
    {
        // From route parameter
        if ($request->route('organisation')) {
            return $request->route('organisation') instanceof Organisation
                ? $request->route('organisation')->id
                : (int) $request->route('organisation');
        }

        // From route parameter (alternative naming)
        if ($request->route('organisation_id')) {
            return (int) $request->route('organisation_id');
        }

        // From request input
        if ($request->has('organisation_id')) {
            return (int) $request->input('organisation_id');
        }

        // From session
        if (Session::has('active_organisation_id')) {
            return (int) Session::get('active_organisation_id');
        }

        // From authenticated user
        if ($request->user() && $request->user()->organisation_id) {
            return (int) $request->user()->organisation_id;
        }

        return null;
    }
}
