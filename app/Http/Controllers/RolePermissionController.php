<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{

    /**
     * Get all permissions.
     *
     * @return JsonResponse
     */
    public function getPermissions()
    {
        if (!auth()->user()->hasPermission('permission.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(Permission::all());
    }

    /**
     * Assign role to user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignRole(Request $request)
    {
        if (!auth()->user()->hasPermission('organisation.assignRole')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name'
        ]);

        $user = User::findOrFail($request->user_id);

        // Check if role belongs to the same organization
        $organisationId = auth()->user()->organisation_id;
        $role = Role::where('name', $request->role)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found in your organization'], 404);
        }

        // Check if user belongs to the same organization
        if ($user->organisation_id !== $organisationId) {
            return response()->json(['message' => 'User is not in your organization'], 403);
        }

        $user->assignRole($role);

        return response()->json(['message' => 'Role assigned successfully']);
    }

    /**
     * Remove role from user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function removeRole(Request $request)
    {
        if (!auth()->user()->hasPermission('organisation.assignRole')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name'
        ]);

        $user = User::findOrFail($request->user_id);

        // Check if user belongs to the same organization
        $organisationId = auth()->user()->organisation_id;
        if ($user->organisation_id !== $organisationId) {
            return response()->json(['message' => 'User is not in your organization'], 403);
        }

        $user->removeRole($request->role);

        return response()->json(['message' => 'Role removed successfully']);
    }

    /**
     * Assign permission to user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignPermission(Request $request)
    {
        if (!auth()->user()->hasPermission('permission.assign')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission' => 'required|exists:permissions,name'
        ]);

        $user = User::findOrFail($request->user_id);

        // Check if user belongs to the same organization
        $organisationId = auth()->user()->organisation_id;
        if ($user->organisation_id !== $organisationId) {
            return response()->json(['message' => 'User is not in your organization'], 403);
        }

        $user->givePermissionTo($request->permission);

        return response()->json(['message' => 'Permission assigned successfully']);
    }

    /**
     * Remove permission from user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function removePermission(Request $request)
    {
        if (!auth()->user()->hasPermission('permission.assign')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission' => 'required|exists:permissions,name'
        ]);

        $user = User::findOrFail($request->user_id);

        // Check if user belongs to the same organization
        $organisationId = auth()->user()->organisation_id;
        if ($user->organisation_id !== $organisationId) {
            return response()->json(['message' => 'User is not in your organization'], 403);
        }

        $user->revokePermissionTo($request->permission);

        return response()->json(['message' => 'Permission removed successfully']);
    }
}
