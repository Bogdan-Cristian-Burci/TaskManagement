<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        // Parse resource and action from permission name
        $parts = explode('.', $this->name);
        $resource = count($parts) >= 2 ? $parts[0] : 'other';
        $action = count($parts) >= 2 ? $parts[1] : $this->name;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'resource' => $resource,
            'action' => $action,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
