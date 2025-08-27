<?php

namespace App\Http\Resources;

use App\Models\BoardType;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BoardType */
class BoardTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'template' => $this->when($this->relationLoaded('template') && $this->template_id, function() {
                $template = \App\Models\BoardTemplate::withoutGlobalScopes()->find($this->template_id);
                return $template ? [
                    'columns_structure' => $template->columns_structure ? $this->formatColumnsStructure($template->columns_structure) : [],
                    'settings' => $template->settings ?? []
                ] : null;
            }),
            'boards' => BoardResource::collection($this->whenLoaded('boards')),
        ];
    }

    /**
     * Format the columns structure for API response.
     *
     * @param array|null $columnsStructure
     * @return array
     */
    protected function formatColumnsStructure(?array $columnsStructure): array
    {
        if (!$columnsStructure) {
            return [];
        }

        return collect($columnsStructure)->map(function($column) {
            // Add status name if status_id is present
            if (isset($column['status_id'])) {
                $status = Status::find($column['status_id']);
                if ($status) {
                    $column['status_name'] = $status->name;
                }
            }

            // Default color if not set
            if (empty($column['color'])) {
                $column['color'] = '#6C757D'; // Default gray
            }

            return $column;
        })->toArray();
    }
}
