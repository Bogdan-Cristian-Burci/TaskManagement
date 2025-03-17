<?php

namespace App\Http\Controllers;

use App\Models\RoleTemplate;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleTemplateController extends Controller
{
    /**
     * Display a listing of templates for the current organization
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;
        $templates = RoleTemplate::forOrganisation($organisationId)
            ->get()
            ->map(function($template) {
                // Count roles using this template
                $rolesCount = Role::where('template_id', $template->id)->count();
                $template->roles_count = $rolesCount;
                return $template;
            });

        return response()->json(['templates' => $templates]);
    }

    /**
     * Store a new template
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'string'
        ]);

        $organisationId = $request->user()->organisation_id;

        // Check for duplicate name
        $exists = RoleTemplate::where('name', $validated['name'])
            ->where('organisation_id', $organisationId)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A template with this name already exists'
            ], 422);
        }

        $template = RoleTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'],
            'organisation_id' => $organisationId
        ]);

        return response()->json([
            'message' => 'Template created successfully',
            'template' => $template
        ], 201);
    }

    /**
     * Display a specific template
     */
    public function show(Request $request, $id): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;
        $template = RoleTemplate::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->firstOrFail();

        // Get roles using this template
        $roles = Role::where('template_id', $template->id)
            ->get(['id', 'name', 'level']);

        return response()->json([
            'template' => $template,
            'roles' => $roles
        ]);
    }

    /**
     * Update a template
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|required|array',
            'permissions.*' => 'string'
        ]);

        $organisationId = $request->user()->organisation_id;
        $template = RoleTemplate::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->firstOrFail();

        // Check for duplicate name if name is being changed
        if (isset($validated['name']) && $validated['name'] !== $template->name) {
            $exists = RoleTemplate::where('name', $validated['name'])
                ->where('organisation_id', $organisationId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'A template with this name already exists'
                ], 422);
            }
        }

        $template->update($validated);

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => $template
        ]);
    }

    /**
     * Delete a template
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;
        $template = RoleTemplate::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->firstOrFail();

        // Check if any roles are using this template
        $rolesUsingTemplate = Role::where('template_id', $template->id)->count();

        if ($rolesUsingTemplate > 0) {
            return response()->json([
                'message' => 'Cannot delete template that is in use by roles',
                'roles_count' => $rolesUsingTemplate
            ], 422);
        }

        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully'
        ]);
    }
}
