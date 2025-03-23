<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganisationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization is handled by policies, so always return true here
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('organisations')->ignore($this->organisation),
            ],
            'unique_id' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('organisations')->ignore($this->organisation),
            ],
        ];

        // Only allow owner_id to be set if user has appropriate permissions
        if ($this->user()->hasPermission('organisation.changeOwner')) {
            $rules['owner_id'] = 'nullable|exists:users,id';
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
            'name.required' => 'An organisation name is required',
            'name.max' => 'Organisation name cannot exceed 255 characters',
            'slug.unique' => 'This slug is already in use by another organisation',
            'unique_id.unique' => 'This unique ID is already in use by another organisation',
            'owner_id.exists' => 'The selected owner does not exist',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // If slug is not provided but name is, generate slug from name
        // This is a backup in case the model boot method doesn't handle it
        if (!$this->has('slug') && $this->has('name')) {
            $this->merge([
                'slug' => \Str::slug($this->name),
            ]);
        }
    }
}
