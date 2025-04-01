<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardTemplateRequest;
use App\Http\Resources\BoardTemplateResource;
use App\Models\BoardTemplate;
use App\Services\BoardTemplateService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BoardTemplateController extends Controller
{
    protected BoardTemplateService $boardTemplateService;

    public function __construct(BoardTemplateService $boardTemplateService)
    {
        $this->middleware('auth:api');
        $this->boardTemplateService = $boardTemplateService;
    }

    /**
     * Display a listing of all templates.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BoardTemplate::class);

        $templates = collect();

        // Include system templates if requested
        if ($request->boolean('include_system', false)) {
            $systemTemplates = $this->boardTemplateService->getSystemTemplates();
            // Use merge() instead of concat() when combining collections
            $templates = $templates->merge($systemTemplates);
        }

        // Add organization templates
        if ($request->user()->organisation_id) {
            $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;
            $orgTemplates = $this->boardTemplateService->getOrganizationTemplates(
                $request->user()->organisation_id,
                $isActive
            );
            // Use merge() instead of concat()
            $templates = $templates->merge($orgTemplates);
        }

        // Sort by name
        $templates = $templates->sortBy('name')->values();

        return BoardTemplateResource::collection($templates);
    }

    /**
     * Get all system templates.
     *
     * @return AnonymousResourceCollection
     */
    public function systemTemplates(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BoardTemplate::class);

        $templates = $this->boardTemplateService->getSystemTemplates();

        return BoardTemplateResource::collection($templates);
    }

    /**
     * Store a newly created custom template.
     *
     * @param BoardTemplateRequest $request
     * @return BoardTemplateResource
     */
    public function store(BoardTemplateRequest $request): BoardTemplateResource
    {
        $this->authorize('create', BoardTemplate::class);

        $validated = $request->validated();

        $template = $this->boardTemplateService->createCustomTemplate(
            $request->input('organisation_id'),
            $validated['name'],
            $validated['description'] ?? '',
            $validated['columns_structure'],
            $validated['settings'] ?? [],
            auth()->id()
        );

        return new BoardTemplateResource($template);
    }

    /**
     * Display the specified template.
     *
     * @param BoardTemplate $boardTemplate
     * @return BoardTemplateResource
     */
    public function show(BoardTemplate $boardTemplate): BoardTemplateResource
    {
        $this->authorize('view', $boardTemplate);

        return new BoardTemplateResource($boardTemplate);
    }

    /**
     * Update the specified template.
     * Only custom templates can be updated.
     *
     * @param BoardTemplateRequest $request
     * @param BoardTemplate $boardTemplate
     * @return BoardTemplateResource
     */
    public function update(BoardTemplateRequest $request, BoardTemplate $boardTemplate): BoardTemplateResource
    {
        $this->authorize('update', $boardTemplate);

        try {
            $template = $this->boardTemplateService->updateCustomTemplate($boardTemplate, $request->validated());
            return new BoardTemplateResource($template);
        } catch (\Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * Remove the specified template from storage.
     * Only custom templates can be deleted.
     *
     * @param BoardTemplate $boardTemplate
     * @return Response
     */
    public function destroy(BoardTemplate $boardTemplate): Response
    {
        $this->authorize('delete', $boardTemplate);

        try {
            $this->boardTemplateService->deleteCustomTemplate($boardTemplate);
            return response()->noContent();
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'System templates cannot be deleted')) {
                return response(['message' => $e->getMessage()], 403);
            }

            if (str_contains($e->getMessage(), 'Template is in use')) {
                preg_match('/by (\d+) board types/', $e->getMessage(), $matches);
                $count = $matches[1] ?? 0;

                return response([
                    'message' => $e->getMessage(),
                    'in_use_count' => (int) $count
                ], 409);
            }

            return response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Duplicate an existing template.
     *
     * @param Request $request
     * @param int $id
     * @return BoardTemplateResource
     */
    public function duplicate(Request $request, int $id): BoardTemplateResource
    {
        try {

            // Get the current user's organization ID
            $currentUserOrgId = OrganizationContext::getCurrentOrganizationId();
            \Log::info('Current user organization ID: ' . $currentUserOrgId);

            if (!$currentUserOrgId) {
                \Log::warning('No organization context found');
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'message' => 'You must belong to an organization to perform this action'
                    ], 403)
                );
            }

            // Check if template exists at the DB level first for better debugging
            $exists = \DB::table('board_templates')->where('id', $id)->exists();

            if (!$exists) {
                \Log::warning('Template with ID ' . $id . ' not found in database');
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                    "Template with ID {$id} not found"
                );
            }

            // Now fetch with all the right scope bypassing
            $boardTemplate = BoardTemplate::withoutGlobalScope('withoutSystem')
                ->withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
                ->find($id);

            // Security check for template ownership
            if (!$boardTemplate->is_system && $boardTemplate->organisation_id !== $currentUserOrgId) {
                \Log::warning('Unauthorized template access attempt', [
                    'template_id' => $id,
                    'template_org' => $boardTemplate->organisation_id,
                    'user_org' => $currentUserOrgId
                ]);

                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You cannot duplicate templates from other organizations'
                );
            }

            // Check policy authorization
            $this->authorize('duplicate', $boardTemplate);

            $request->validate([
                'name' => 'sometimes|string|max:255'
            ]);

            // Create the duplicate
            $newTemplate = $this->boardTemplateService->duplicateTemplate(
                $boardTemplate,
                $currentUserOrgId,
                $request->input('name'),
                auth()->id()
            );

            return new BoardTemplateResource($newTemplate);
        } catch (\Exception $e) {
            \Log::error('Exception in duplicate method', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Toggle the active state of a template.
     *
     * @param BoardTemplate $boardTemplate
     * @return BoardTemplateResource
     */
    public function toggleActive(BoardTemplate $boardTemplate): BoardTemplateResource
    {
        $this->authorize('update', $boardTemplate);

        $template = $this->boardTemplateService->toggleActiveState($boardTemplate);

        return new BoardTemplateResource($template);
    }

    /**
     * Force sync system templates from config (admin only).
     *
     * @return JsonResponse
     */
    public function syncSystem(): JsonResponse
    {
        $this->authorize('admin', BoardTemplate::class);

        $count = $this->boardTemplateService->syncSystemTemplates();

        return response()->json([
            'message' => 'System templates synced successfully',
            'templates_created' => $count
        ]);
    }
}
