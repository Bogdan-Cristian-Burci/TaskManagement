<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\RoleTemplate;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('role.update', $this->user()->organisation_id);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $organisationId = $this->user()->organisation_id;

        return [
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'level' => 'nullable|integer|min:1|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roleId = $this->route('id');
            $organisationId = $this->user()->organisation_id;

            // Find the role
            $role = Role::where('id', $roleId)
                ->where(function($query) use ($organisationId) {
                    // Either org-specific role
                    $query->where('organisation_id', $organisationId)
                        // Or system role not overridden for this org
                        ->orWhere(function($q) use ($organisationId) {
                            $q->whereNull('organisation_id')
                                ->whereNotExists(function($subq) use ($organisationId, $roleId) {
                                    $subq->select(DB::raw(1))
                                        ->from('roles')
                                        ->where('system_role_id', $roleId)
                                        ->where('organisation_id', $organisationId);
                                });
                        });
                })
                ->with('template')
                ->first();

            // If role doesn't exist or isn't accessible to this org
            if (!$role) {
                $validator->errors()->add('id', 'Role not found or not accessible.');
                return;
            }

            // Don't allow editing system's admin role
            if ($role->template &&
                $role->template->name === 'admin' &&
                $role->template->is_system) {

                // If permissions are empty or don't contain critical admin permissions
                if ($this->has('permissions')) {
                    $adminPermissions = ['manage-roles', 'manage-permissions', 'user.update', 'user.delete'];
                    $providedPermissions = $this->input('permissions');

                    $missingCritical = false;
                    foreach ($adminPermissions as $critical) {
                        if (!in_array($critical, $providedPermissions)) {
                            $missingCritical = true;
                            break;
                        }
                    }

                    if ($missingCritical) {
                        $validator->errors()->add(
                            'permissions',
                            'Cannot remove critical permissions from admin role.'
                        );
                    }
                }

                // Don't allow changing admin role display name
                if ($this->input('display_name') &&
                    $this->input('display_name') !== $role->template->display_name) {
                    $validator->errors()->add(
                        'display_name',
                        'Cannot change admin role display name.'
                    );
                }
            }

            // If this is a system role (not org-specific), validate that we have permissions
            // because we'll be creating an override
            if ($role->organisation_id === null && !$this->has('permissions')) {
                $validator->errors()->add(
                    'permissions',
                    'Permissions are required when modifying a system role.'
                );
            }
        });
    }
}
