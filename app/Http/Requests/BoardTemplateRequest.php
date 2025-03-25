<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'columns_structure' => 'required|array|min:1',
            'columns_structure.*.name' => 'required|string|max:100',
            'columns_structure.*.color' => 'nullable|string|max:50',
            'columns_structure.*.wip_limit' => 'nullable|integer|min:1',
            'columns_structure.*.status_id' => 'nullable|exists:statuses,id',
            'columns_structure.*.allowed_transitions' => 'nullable|array',
            'columns_structure.*.allowed_transitions.*' => 'integer',
            'settings' => 'nullable|array',
            'organisation_id' => 'required|exists:organisations,id',
        ];

        // If we're updating, prevent changing the organisation_id of system templates
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $template = $this->route('boardTemplate');

            if ($template && $template->is_system) {
                $rules['organisation_id'] = [
                    'sometimes',
                    'exists:organisations,id',
                    Rule::in([$template->organisation_id]),
                ];
                $rules['is_system'] = 'prohibited';
            }
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'columns_structure.required' => 'At least one column must be defined',
            'columns_structure.min' => 'At least one column must be defined',
            'columns_structure.*.name.required' => 'Each column must have a name',
            'organisation_id.in' => 'Cannot change the organization of a system template',
            'is_system.prohibited' => 'Cannot change the system status of a template',
        ];
    }
}
