<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('role.update');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $roleId = $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $roleId . ',id,organisation_id,' . $this->user()->organisation_id,
            'description' => 'nullable|string|max:1000',
            'level' => 'nullable|integer|min:1|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $roleId = $this->route('id');
            $role = DB::table('roles')
                ->where('id', $roleId)
                ->first();

            // Don't allow renaming the 'admin' role
            if ($role && $role->name === 'admin' && $this->input('name') && $this->input('name') !== 'admin') {
                $validator->errors()->add('name', 'Cannot rename the admin role.');
            }
        });
    }
}
