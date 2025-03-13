<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Organisation;

class OrganizationContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get organization from request
        $orgId = $this->getOrganizationFromRequest($request);

        // Store in session for later use
        if ($orgId) {
            session(['current_organization_id' => $orgId]);

            // Set organization ID on user object if authenticated
            if ($request->user()) {
                $request->user()->organisation_id = $orgId;
            }
        }

        return $next($request);
    }

    /**
     * Get organization ID from request
     */
    protected function getOrganizationFromRequest(Request $request)
    {
        // From route parameter
        if ($request->route('organisation')) {
            return $request->route('organisation') instanceof Organisation
                ? $request->route('organisation')->id
                : $request->route('organisation');
        }

        // From request input
        if ($request->has('organisation_id')) {
            return $request->input('organisation_id');
        }

        // From session
        if (session('current_organization_id')) {
            return session('current_organization_id');
        }

        // From authenticated user
        if ($request->user() && $request->user()->organisation_id) {
            return $request->user()->organisation_id;
        }

        return null;
    }
}
