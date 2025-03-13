<?php

namespace App\Http\Middleware;

use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;

class SetOrganizationContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Try to determine organization from various sources
        $organizationId = null;

        // From route parameter
        if ($request->route('organisation') !== null) {
            $organizationId = $request->route('organisation') instanceof \App\Models\Organisation
                ? $request->route('organisation')->id
                : $request->route('organisation');
        }
        // From query string
        elseif ($request->has('organisation_id')) {
            $organizationId = $request->input('organisation_id');
        }
        // From request data
        elseif ($request->has('organisation_id')) {
            $organizationId = $request->input('organisation_id');
        }
        // From authenticated user
        elseif ($request->user() && $request->user()->organisation_id) {
            $organizationId = $request->user()->organisation_id;
        }

        // Set the organization context
        OrganizationContext::setCurrentOrganization($organizationId);

        $response = $next($request);

        // Clear the context after the request is processed
        OrganizationContext::clear();

        return $response;
    }
}
