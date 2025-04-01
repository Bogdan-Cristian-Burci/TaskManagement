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

        \Log::debug('Starting template duplication', ['template_id' => $id]);

        // Get the current user's organization ID
        $currentUserOrgId = OrganizationContext::getCurrentOrganizationId();

        if (!$currentUserOrgId) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'You must belong to an organization to perform this action'
                ], 403)
            );
        }
        \Log::info('BoardTemplate id is: ' . $id.' and current user org id is: ' . $currentUserOrgId);
        // Fetch the board template without global scopes

        $boardTemplate = BoardTemplate::withoutGlobalScopes()->find($id);

        // Security check: Only allow duplication if the template is:
        // 1. A system template (organization_id is null, is_system is true)
        // 2. Belongs to the user's current organization
        if (!$boardTemplate->is_system && $boardTemplate->organisation_id !== $currentUserOrgId) {
            \Log::warning('Unauthorized template duplication attempt', [
                'user_id' => auth()->id(),
                'user_org' => $currentUserOrgId,
                'template_id' => $id,
                'template_org' => $boardTemplate->organisation_id
            ]);

            throw new \Illuminate\Auth\Access\AuthorizationException(
                'You cannot duplicate templates from other organizations'
            );
        }

        $this->authorize('duplicate', $boardTemplate);

        $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $newTemplate = $this->boardTemplateService->duplicateTemplate(
            $boardTemplate,
            $currentUserOrgId,
            $request->input('name'),
            auth()->id()
        );

        return new BoardTemplateResource($newTemplate);
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
