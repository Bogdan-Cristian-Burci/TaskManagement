<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SetActiveOrganisationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Get organization from route or query string if present
            $orgId = $request->route('organisation_id') ?? $request->query('organisation_id');

            if ($orgId) {
                // Check if user belongs to this organization
                if ($user->organisations()->where('organisations.id', $orgId)->exists()) {
                    // Set this as active organization
                    $user->organisation_id = $orgId;
                    $user->save();

                    // Store in session too
                    Session::put('active_organisation_id', $orgId);
                }
            } elseif (!$user->organisation_id) {
                // If no active organization is set, use the first one
                $firstOrg = $user->organisations()->first();

                if ($firstOrg) {
                    $user->organisation_id = $firstOrg->id;
                    $user->save();

                    // Store in session too
                    Session::put('active_organisation_id', $firstOrg->id);
                }
            }
        }

        return $next($request);
    }
}
