<?php

namespace App\Http\Requests;

use App\Models\ChangeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // For store requests
        if ($this->isMethod('POST')) {
            return $this->user()->can('create', ChangeType::class);
        }

        // For update requests
        $changeType = $this->route('changeType');
        if ($changeType && ($this->isMethod('PUT') || $this->isMethod('PATCH'))) {
            return $this->user()->can('update', $changeType);
        }

        // For delete requests
        if ($changeType && $this->isMethod('DELETE')) {
            return $this->user()->can('delete', $changeType);
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ];

        // Add unique rule for name, except for the current change type on updates
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $changeType = $this->route('changeType');
            $rules['name'][] = Rule::unique('change_types')->ignore($changeType);
        } else {
            $rules['name'][] = 'unique:change_types';
        }

        // Make fields optional for updates
        if ($this->isMethod('PATCH')) {
            $rules = collect($rules)->map(function ($rule) {
                return array_filter($rule, function ($item) {
                    return $item !== 'required';
                });
            })->all();
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The change type name is required.',
            'name.unique' => 'This change type name is already in use.',
            'description.required' => 'The change type description is required.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Trim string inputs
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }

        if ($this->has('description')) {
            $this->merge(['description' => trim($this->description)]);
        }
    }
}
