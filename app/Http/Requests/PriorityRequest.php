<?php

namespace App\Http\Requests;

use App\Models\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriorityRequest extends FormRequest
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
            return $this->user()->hasPermission('create', Priority::class);
        }

        // For update requests
        $priority = $this->route('priority');
        if ($priority && ($this->isMethod('PUT') || $this->isMethod('PATCH'))) {
            return $this->user()->hasPermission('update', $priority);
        }

        // For delete requests
        if ($priority && $this->isMethod('DELETE')) {
            return $this->user()->hasPermission('delete', $priority);
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
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'level' => ['nullable', 'integer', 'min:1'],
        ];

        // Add unique rule for name, except for the current priority on updates
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $priority = $this->route('priority');
            $rules['name'][] = Rule::unique('priorities')->ignore($priority);
            // For level uniqueness check on update
            if ($this->has('level')) {
                $rules['level'][] = Rule::unique('priorities')->ignore($priority);
            }
        } else {
            $rules['name'][] = 'unique:priorities';
            // For level uniqueness check on creation
            if ($this->has('level')) {
                $rules['level'][] = 'unique:priorities';
            }
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
            'name.required' => 'The priority name is required.',
            'name.unique' => 'This priority name is already in use.',
            'level.unique' => 'This priority level is already in use.',
            'level.min' => 'The priority level must be at least 1.',
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
