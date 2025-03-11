<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        // Get permissions for this role using direct database query
        $permissions = DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $this->id)
            ->pluck('permissions.name')
            ->toArray();

        // Count users with this role
        $usersCount = DB::table('model_has_roles')
            ->where('role_id', $this->id)
            ->where('organisation_id', $this->organisation_id)
            ->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'description' => $this->description,
            'level' => $this->level,
            'organisation_id' => $this->organisation_id,
            'permissions' => $permissions,
            'users_count' => $usersCount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'links' => [
                'self' => route('roles.show', $this->id),
                'users' => route('roles.users', $this->id),
            ],
        ];
    }
}
