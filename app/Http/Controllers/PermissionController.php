<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * Display a listing of available permissions
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('permission.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get all permissions
        $permissions = DB::table('permissions')
            ->orderBy('name')
            ->get();

        // Group permissions by resource
        $grouped = [];

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);

            if (count($parts) >= 2) {
                $resource = $parts[0];
                $action = $parts[1];

                if (!isset($grouped[$resource])) {
                    $grouped[$resource] = [];
                }

                $grouped[$resource][] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'action' => $action
                ];
            } else {
                // Handle non-standard permission names
                if (!isset($grouped['other'])) {
                    $grouped['other'] = [];
                }

                $grouped['other'][] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'action' => 'other'
                ];
            }
        }

        return response()->json([
            'permissions' => $grouped
        ]);
    }

    /**
     * Get all available permission categories with their actions
     * This is useful for building permission selection UI
     *
     * @param Request $request
     * @param bool $checkExistence Whether to check if permissions exist in the database
     * @return JsonResponse
     */
    public function categories(Request $request, bool $checkExistence = true): JsonResponse
    {
        if (!$request->user()->hasPermission('permission.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Use cache to improve performance - different cache key based on whether we're checking existence
        $cacheKey = 'permission_categories' . ($checkExistence ? '_with_existence' : '');

        $result = Cache::remember($cacheKey, 3600, function () use ($checkExistence) {
            // Get config values - convert indexed arrays to associative if needed
            $standardActions = config('permissions.standard_actions');
            $extendedActions = config('permissions.extended_actions');
            $models = config('permissions.models');

            // Create display names for models (capitalize first letter)
            $modelDisplayNames = [];
            foreach ($models as $model) {
                $modelDisplayNames[$model] = ucfirst($model);
            }

            // Create display names for standard actions (capitalize first letter)
            $standardActionDisplayNames = [];
            foreach ($standardActions as $action) {
                // Convert camelCase to Title Case with spaces
                $displayName = ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $action));
                $standardActionDisplayNames[$action] = $displayName;
            }

            $result = [];

            // Build the complete permissions structure
            foreach ($models as $modelIndex => $model) {
                $permissionActions = [];

                // Add standard actions with proper display names
                foreach ($standardActions as $actionIndex => $action) {
                    $permissionActions[$action] = $standardActionDisplayNames[$action];
                }

                // Add extended actions if they exist for this model
                if (isset($extendedActions[$model])) {
                    foreach ($extendedActions[$model] as $action) {
                        // Convert camelCase to Title Case with spaces
                        $displayName = ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $action));
                        $permissionActions[$action] = $displayName;
                    }
                }

                $categoryPermissions = [];
                foreach ($permissionActions as $action => $displayName) {
                    $permissionName = $model . '.' . $action;

                    $permissionData = [
                        'name' => $permissionName,
                        'action' => $action,
                        'display_name' => $displayName,
                    ];

                    // Only check existence if requested
                    if ($checkExistence) {
                        $permissionData['exists'] = DB::table('permissions')
                            ->where('name', $permissionName)
                            ->exists();
                    }

                    $categoryPermissions[] = $permissionData;
                }

                $result[] = [
                    'category' => $model,
                    'display_name' => $modelDisplayNames[$model],
                    'permissions' => $categoryPermissions
                ];
            }

            return $result;
        });

        return response()->json([
            'permission_categories' => $result
        ]);
    }
    /**
     * Get all available permissions categorized without checking existence
     * Alias for categories method with checkExistence = false
     */
    public function availablePermissions(Request $request): JsonResponse
    {
        return $this->categories($request, false);
    }

    /**
     * List all permissions that can be granted/denied
     * Moved from UserPermissionOverrideController to consolidate permission listing
     */
    public function listAvailablePermissions(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('permissions.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get all permissions
        $permissions = DB::table('permissions')
            ->select('id', 'name', 'guard_name', 'created_at')
            ->orderBy('name')
            ->get();

        // Group permissions by area (e.g., users.create, users.edit -> users area)
        $groupedPermissions = [];
        foreach ($permissions as $permission) {
            $area = explode('.', $permission->name)[0] ?? 'other';
            if (!isset($groupedPermissions[$area])) {
                $groupedPermissions[$area] = [];
            }
            $groupedPermissions[$area][] = $permission;
        }

        return response()->json([
            'permissions' => $permissions,
            'grouped_permissions' => $groupedPermissions
        ]);
    }
}
