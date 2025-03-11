<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserRoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        // Get the user's roles using direct DB query
        $roles = DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.organisation_id', $this->organisation_id)
            ->select('roles.id', 'roles.name', 'roles.level', 'roles.description')
            ->get();

        return [
            'user_id' => $this->id,
            'user_name' => $this->name,
            'user_email' => $this->email,
            'organisation_id' => $this->organisation_id,
            'roles' => $roles,
        ];
    }
}
