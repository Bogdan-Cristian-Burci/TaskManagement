<?php

namespace App\Services;

use App\Models\BoardTemplate;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BoardTemplateService
{
    /**
     * Get all system templates.
     *
     * @param bool $onlyActive Filter by active status
     * @return Collection
     */
    public function getSystemTemplates(bool $onlyActive = false): Collection
    {
        $templates = BoardTemplate::getSystemTemplates();

        if ($onlyActive) {
            return $templates->where('is_active', true);
        }

        return $templates;
    }

    /**
     * Get all organization templates.
     *
     * @param int $organisationId
     * @param bool|null $isActive Filter by active status
     * @return Collection
     */
    public function getOrganizationTemplates(int $organisationId, ?bool $isActive = null): Collection
    {
        $query = BoardTemplate::where('organisation_id', $organisationId)
            ->where('is_system', false);

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Force sync system templates from config.
     *
     * @return int Number of templates created/updated
     */
    public function syncSystemTemplates(): int
    {
        $beforeCount = BoardTemplate::where('is_system', true)->count();
        BoardTemplate::syncFromConfig();
        $afterCount = BoardTemplate::where('is_system', true)->count();

        return $afterCount - $beforeCount;
    }

    /**
     * Create a custom template.
     *
     * @param int $organisationId
     * @param string $name
     * @param string $description
     * @param array $columnsStructure
     * @param array $settings
     * @param int|null $createdBy
     * @return BoardTemplate
     */
    public function createCustomTemplate(
        int $organisationId,
        string $name,
        string $description,
        array $columnsStructure,
        array $settings = [],
        ?int $createdBy = null
    ): BoardTemplate
    {
        return BoardTemplate::createCustom(
            $organisationId,
            $name,
            $description,
            $columnsStructure,
            $settings,
            $createdBy
        );
    }

    /**
     * Update a custom template.
     * System templates cannot be updated through this method.
     *
     * @param BoardTemplate $boardTemplate
     * @param array $attributes
     * @return BoardTemplate
     * @throws \Exception If attempting to update a system template
     */
    public function updateCustomTemplate(BoardTemplate $boardTemplate, array $attributes): BoardTemplate
    {
        if ($boardTemplate->is_system) {
            throw new \Exception('System templates cannot be modified');
        }

        $boardTemplate->update($attributes);
        return $boardTemplate->fresh();
    }

    /**
     * Delete a custom template.
     * System templates cannot be deleted through this method.
     *
     * @param BoardTemplate $boardTemplate
     * @return bool
     * @throws \Exception If template is a system template or is in use
     */
    public function deleteCustomTemplate(BoardTemplate $boardTemplate): bool
    {
        if ($boardTemplate->is_system) {
            throw new \Exception('System templates cannot be deleted');
        }

        $inUseCount = $boardTemplate->boardTypes()->count();
        if ($inUseCount > 0) {
            throw new \Exception('Template is in use by ' . $inUseCount . ' board types');
        }

        return (bool) $boardTemplate->delete();
    }

    /**
     * Duplicate a template (system or custom).
     *
     * @param int | BoardTemplate $boardTemplate
     * @param int $organisationId
     * @param string|null $newName
     * @param int|null $createdBy
     * @return BoardTemplate
     */
    public function duplicateTemplate(
        int|BoardTemplate $boardTemplate,
        int               $organisationId,
        ?string           $newName = null,
        ?int              $createdBy = null
    ): BoardTemplate
    {
        // If we got an ID instead of a model, fetch the template without global scopes
        if (!($boardTemplate instanceof BoardTemplate)) {
            $boardTemplate = BoardTemplate::withoutGlobalScopes()->findOrFail($boardTemplate);
        }

        return BoardTemplate::duplicateExisting(
            $organisationId,
            $boardTemplate->id,
            $newName,
            $createdBy
        );
    }

    /**
     * Toggle the active state of a template.
     *
     * @param BoardTemplate $boardTemplate
     * @return BoardTemplate
     */
    public function toggleActiveState(BoardTemplate $boardTemplate): BoardTemplate
    {
        if ($boardTemplate->is_system) {
                throw new \Exception('Cannot toggle system template state');
        }
        $boardTemplate->is_active = !$boardTemplate->is_active;
        $boardTemplate->save();

        \Log::info('Template active state toggled in service', [
            'id' => $boardTemplate->id,
            'is_active' => $boardTemplate->is_active ? 'active' : 'inactive'
        ]);

        return $boardTemplate->fresh();
    }
}
