<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * Display a listing of available permissions
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('permission.view')) {
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
     */
    public function categories(): JsonResponse
    {
        if (!request()->user()->canWithOrg('permission.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Define standard permission actions
        $standardActions = [
            'viewAny' => 'View list',
            'view' => 'View details',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'forceDelete' => 'Force delete',
            'restore' => 'Restore'
        ];

        // Define special permissions by category
        $specialPermissions = [
            'project' => [
                'addMember' => 'Add member',
                'removeMember' => 'Remove member',
                'changeOwner' => 'Change owner'
            ],
            'task' => [
                'assign' => 'Assign task',
                'changeStatus' => 'Change status',
                'changePriority' => 'Change priority',
                'addLabel' => 'Add label',
                'removeLabel' => 'Remove label',
                'moveTask' => 'Move task',
                'attachFile' => 'Attach file',
                'detachFile' => 'Detach file'
            ],
            'organisation' => [
                'inviteUser' => 'Invite user',
                'removeUser' => 'Remove user',
                'assignRole' => 'Assign role',
                'viewMetrics' => 'View metrics',
                'manageSettings' => 'Manage settings',
                'exportData' => 'Export data'
            ],
            'board' => [
                'reorderColumns' => 'Reorder columns',
                'addColumn' => 'Add column',
                'changeColumSettings' => 'Change column settings'
            ],
            'permission' => [
                'assign' => 'Assign permission'
            ]
        ];

        // Define categories and their human-readable names
        $categories = [
            'project' => 'Projects',
            'task' => 'Tasks',
            'user' => 'Users',
            'organisation' => 'Organizations',
            'board' => 'Boards',
            'status' => 'Statuses',
            'priority' => 'Priorities',
            'taskType' => 'Task Types',
            'comment' => 'Comments',
            'attachment' => 'Attachments',
            'notification' => 'Notifications',
            'team' => 'Teams',
            'role' => 'Roles',
            'permission' => 'Permissions'
        ];

        $result = [];

        // Build the complete permissions structure
        foreach ($categories as $category => $displayName) {
            $permissionActions = $standardActions;

            // Add special permissions if they exist for this category
            if (isset($specialPermissions[$category])) {
                $permissionActions = array_merge($permissionActions, $specialPermissions[$category]);
            }

            $categoryPermissions = [];
            foreach ($permissionActions as $action => $actionName) {
                $permissionName = $category . '.' . $action;

                // Check if this permission exists in the database
                $exists = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->exists();

                $categoryPermissions[] = [
                    'name' => $permissionName,
                    'action' => $action,
                    'display_name' => $actionName,
                    'exists' => $exists
                ];
            }

            $result[] = [
                'category' => $category,
                'display_name' => $displayName,
                'permissions' => $categoryPermissions
            ];
        }

        return response()->json([
            'permission_categories' => $result
        ]);
    }

    /**
     * Get all available permissions categorized
     */
    public function availablePermissions(Request $request): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Define standard permission actions
        $standardActions = [
            'viewAny' => 'View list',
            'view' => 'View details',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'forceDelete' => 'Force delete',
            'restore' => 'Restore'
        ];

        // Define special permissions by category
        $specialPermissions = [
            'project' => [
                'addMember' => 'Add member',
                'removeMember' => 'Remove member',
                'changeOwner' => 'Change owner'
            ],
            'task' => [
                'assign' => 'Assign task',
                'changeStatus' => 'Change status',
                'changePriority' => 'Change priority',
                'addLabel' => 'Add label',
                'removeLabel' => 'Remove label',
                'moveTask' => 'Move task',
                'attachFile' => 'Attach file',
                'detachFile' => 'Detach file'
            ],
            'organisation' => [
                'inviteUser' => 'Invite user',
                'removeUser' => 'Remove user',
                'assignRole' => 'Assign role',
                'viewMetrics' => 'View metrics',
                'manageSettings' => 'Manage settings',
                'exportData' => 'Export data'
            ],
            'board' => [
                'reorderColumns' => 'Reorder columns',
                'addColumn' => 'Add column',
                'changeColumSettings' => 'Change column settings'
            ],
            'permission' => [
                'assign' => 'Assign permission'
            ]
        ];

        // Define categories
        $categories = [
            'project' => 'Projects',
            'task' => 'Tasks',
            'user' => 'Users',
            'organisation' => 'Organizations',
            'board' => 'Boards',
            'status' => 'Statuses',
            'priority' => 'Priorities',
            'taskType' => 'Task Types',
            'comment' => 'Comments',
            'attachment' => 'Attachments',
            'notification' => 'Notifications',
            'team' => 'Teams',
            'role' => 'Roles',
            'permission' => 'Permissions'
        ];

        $result = [];

        // Build the complete permissions structure
        foreach ($categories as $category => $displayName) {
            $permissionActions = $standardActions;

            // Add special permissions if they exist
            if (isset($specialPermissions[$category])) {
                $permissionActions = array_merge($permissionActions, $specialPermissions[$category]);
            }

            $categoryPermissions = [];
            foreach ($permissionActions as $action => $actionName) {
                $permissionName = $category . '.' . $action;
                $categoryPermissions[] = [
                    'name' => $permissionName,
                    'display_name' => $actionName
                ];
            }

            $result[] = [
                'category' => $category,
                'display_name' => $displayName,
                'permissions' => $categoryPermissions
            ];
        }

        return response()->json([
            'permission_categories' => $result
        ]);
    }
}
