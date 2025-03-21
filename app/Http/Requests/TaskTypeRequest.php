<?php

namespace App\Http\Requests;

use App\Models\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskTypeRequest extends FormRequest
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
            return $this->user()->hasPermission('create', TaskType::class);
        }

        // For update requests
        $taskType = $this->route('taskType');
        if ($taskType && ($this->isMethod('PUT') || $this->isMethod('PATCH'))) {
            return $this->user()->hasPermission('update', $taskType);
        }

        // For delete requests
        if ($taskType && $this->isMethod('DELETE')) {
            return $this->user()->hasPermission('delete', $taskType);
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
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ];

        // Add unique rule for name, except for the current task type on updates
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $taskType = $this->route('taskType');
            $rules['name'][] = Rule::unique('task_types')->ignore($taskType);
        } else {
            $rules['name'][] = 'unique:task_types';
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
            'name.required' => 'The task type name is required.',
            'name.unique' => 'This task type name is already in use.',
            'description.required' => 'The task type description is required.',
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
