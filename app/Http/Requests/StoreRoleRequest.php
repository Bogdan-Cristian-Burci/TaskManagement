<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\RoleTemplate;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('role.create', $this->user()->organisation_id);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $organisationId = $this->user()->organisation_id;

        // Base rules
        $rules = [
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'level' => 'nullable|integer|min:1|max:100',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ];

        // The request must have either system_template_id (for override)
        // or name + permissions (for custom role)
        $rules['system_template_id'] = [
            'nullable',
            'integer',
            Rule::exists('role_templates', 'id')->where(function ($query) {
                $query->where('is_system', true);
            }),
            'required_without:name'
        ];

        $rules['name'] = [
            'nullable',
            'string',
            'max:255',
            'required_without:system_template_id',
            // If creating a custom role, name must be unique for this organization
            Rule::unique('role_templates', 'name')
                ->where(function ($query) use ($organisationId) {
                    $query->where('organisation_id', $organisationId);
                })
        ];

        // When creating a custom role, permissions are required
        if (!$this->has('system_template_id')) {
            $rules['permissions'] = 'required|array';
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required_without' => 'The name field is required when creating a custom role.',
            'system_template_id.required_without' => 'Either a system template ID or a name for a custom role must be provided.',
            'permissions.required' => 'Permissions are required when creating a custom role.',
            'name.unique' => 'A role with this name already exists in your organization.',
            'system_template_id.exists' => 'The selected system template does not exist.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $organisationId = $this->user()->organisation_id;
            $systemTemplateId = $this->input('system_template_id');

            // If system_template_id is provided, check if an override already exists
            if ($systemTemplateId) {
                $systemTemplate = RoleTemplate::find($systemTemplateId);

                if ($systemTemplate) {
                    // Check if an override template already exists
                    $existingOverride = RoleTemplate::where('name', $systemTemplate->name)
                        ->where('organisation_id', $organisationId)
                        ->exists();

                    if ($existingOverride) {
                        $validator->errors()->add(
                            'system_template_id',
                            'An override for this system template already exists in your organization.'
                        );
                    }
                }
            }
        });
    }
}
