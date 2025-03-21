<?php

namespace App\Http\Requests;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusRequest extends FormRequest
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
            return $this->user()->hasPermission('create', Status::class);
        }

        // For update requests
        $status = $this->route('status');
        if ($status && ($this->isMethod('PUT') || $this->isMethod('PATCH'))) {
            return $this->user()->hasPermission('update', $status);
        }

        // For delete requests
        if ($status && $this->isMethod('DELETE')) {
            return $this->user()->hasPermission('delete', $status);
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
            'is_default' => ['boolean'],
            'position' => ['integer', 'min:1'],
            'category' => ['nullable', 'string', 'in:todo,in_progress,done,canceled'],
        ];

        // Add unique rule for name, except for the current status on updates
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $status = $this->route('status');
            $rules['name'][] = Rule::unique('statuses')->ignore($status);
        } else {
            $rules['name'][] = 'unique:statuses';
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
            'name.required' => 'The status name is required.',
            'name.unique' => 'This status name is already in use.',
            'category.in' => 'The category must be one of: todo, in_progress, done, or canceled.',
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
