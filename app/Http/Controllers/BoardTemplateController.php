<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardTemplateRequest;
use App\Http\Resources\BoardTemplateResource;
use App\Models\BoardTemplate;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BoardTemplateController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of the templates.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BoardTemplate::class);

        $query = BoardTemplate::query();

        // Include system templates if requested
        if ($request->boolean('include_system', false)) {
            $query->withoutGlobalScope('withoutSystem');
        }

        // Filter by active state
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $templates = $query->orderBy('name')->get();

        return BoardTemplateResource::collection($templates);
    }

    /**
     * Store a newly created template in storage.
     *
     * @param BoardTemplateRequest $request
     * @return BoardTemplateResource
     */
    public function store(BoardTemplateRequest $request): BoardTemplateResource
    {
        $this->authorize('create', BoardTemplate::class);

        $validated = $request->validated();

        // Get organization ID from current user
        $organisation = Organisation::findOrFail($request->input('organisation_id'));

        $template = BoardTemplate::createCustom(
            $organisation->id,
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
     * Update the specified template in storage.
     *
     * @param BoardTemplateRequest $request
     * @param BoardTemplate $boardTemplate
     * @return BoardTemplateResource
     */
    public function update(BoardTemplateRequest $request, BoardTemplate $boardTemplate): BoardTemplateResource
    {
        $this->authorize('update', $boardTemplate);

        $validated = $request->validated();

        // Update the template
        $boardTemplate->update($validated);

        return new BoardTemplateResource($boardTemplate);
    }

    /**
     * Remove the specified template from storage.
     *
     * @param BoardTemplate $boardTemplate
     * @return Response
     */
    public function destroy(BoardTemplate $boardTemplate): Response
    {
        $this->authorize('delete', $boardTemplate);

        // Prevent deletion of system templates
        if ($boardTemplate->is_system) {
            return response(['message' => 'Cannot delete system templates'], 403);
        }

        // Check if this template is in use
        $inUseCount = $boardTemplate->boardTypes()->count();
        if ($inUseCount > 0) {
            return response([
                'message' => 'Template is in use by ' . $inUseCount . ' board types and cannot be deleted',
                'in_use_count' => $inUseCount
            ], 409);
        }

        $boardTemplate->delete();

        return response()->noContent();
    }

    /**
     * Duplicate an existing template.
     *
     * @param Request $request
     * @param BoardTemplate $boardTemplate
     * @return BoardTemplateResource
     */
    public function duplicate(Request $request, BoardTemplate $boardTemplate): BoardTemplateResource
    {
        $this->authorize('duplicate', $boardTemplate);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'organisation_id' => 'required|exists:organisations,id'
        ]);

        $newName = $request->input('name');
        $organisationId = $request->input('organisation_id');

        // Duplicate the template
        $newTemplate = BoardTemplate::duplicateExisting(
            $organisationId,
            $boardTemplate->id,
            $newName,
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

        $boardTemplate->is_active = !$boardTemplate->is_active;
        $boardTemplate->save();

        return new BoardTemplateResource($boardTemplate);
    }

    /**
     * Get all system templates.
     *
     * @return AnonymousResourceCollection
     */
    public function systemTemplates(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BoardTemplate::class);

        $templates = BoardTemplate::getSystemTemplates();

        return BoardTemplateResource::collection($templates);
    }
}
