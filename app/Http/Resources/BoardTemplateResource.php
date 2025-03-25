<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class BoardTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'key' => $this->key,
            'columns_structure' => $this->formatColumnsStructure($this->columns_structure),
            'settings' => $this->settings,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,

            // Include organization data only if available (system templates may not have an org)
            'organisation' => $this->whenLoaded('organisation', function() {
                return [
                    'id' => $this->organisation->id,
                    'name' => $this->organisation->name,
                ];
            }),

            // Include creator information
            'created_by' => $this->whenLoaded('createdBy', function() {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                    'email' => $this->createdBy->email,
                ];
            }),

            // Usage statistics
            'boards_count' => $this->when($request->has('with_stats'), function() {
                return $this->boardTypes->sum(function($boardType) {
                    return $boardType->boards()->count();
                });
            }),

            // Permission-based actions available to the current user
            'can' => [
                'update' => Auth::check() ? Auth::user()->can('update', $this->resource) : false,
                'delete' => Auth::check() ? Auth::user()->can('delete', $this->resource) : false,
                'duplicate' => Auth::check() ? Auth::user()->can('duplicate', $this->resource) : false,
            ],

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format the columns structure for API response.
     *
     * This enhances the columns data with status names and other useful information.
     *
     * @param array|null $columnsStructure
     * @return array
     */
    protected function formatColumnsStructure(?array $columnsStructure): array
    {
        if (!$columnsStructure) {
            return [];
        }

        return collect($columnsStructure)->map(function($column) use ($columnsStructure) {
            // Add status name if status_id is present
            if (isset($column['status_id'])) {
                $status = \App\Models\Status::find($column['status_id']);
                if ($status) {
                    $column['status_name'] = $status->name;
                }
            }

            // Format allowed transitions if present
            if (isset($column['allowed_transitions']) && is_array($column['allowed_transitions'])) {
                // Map column indices to status names where possible
                $column['allowed_transition_names'] = collect($column['allowed_transitions'])
                    ->map(function($columnIndex) use ($columnsStructure) {
                        // Adjust for zero-based index if needed
                        $index = $columnIndex - 1;
                        return $columnsStructure[$index]['name'] ?? "Column $columnIndex";
                    })
                    ->toArray();
            }

            // Default color if not set
            if (!isset($column['color']) || empty($column['color'])) {
                $column['color'] = '#6C757D'; // Default gray
            }

            return $column;
        })->toArray();
    }
}
