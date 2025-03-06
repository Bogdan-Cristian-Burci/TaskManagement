<?php

namespace App\Http\Requests;

use App\Models\Organisation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganisationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organisationId = $this->route('organisation') ? $this->route('organisation')->id : null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash', // Only allow alphanumeric chars, dashes and underscores
                Rule::unique('organisations', 'slug')->ignore($organisationId),
            ],
            'unique_id' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('organisations', 'unique_id')->ignore($organisationId),
            ],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:1024'], // Limit URL length
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'owner_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    // If changing owner, ensure the new owner is a member of the organisation
                    if ($this->route('organisation') && $value !== $this->route('organisation')->owner_id) {
                        if (!$this->route('organisation')->hasMember($value)) {
                            $fail('The new owner must be a member of the organisation.');
                        }

                        // Only allow owner changes if user has permission
                        if (!$this->user()->can('changeOwner', $this->route('organisation'))) {
                            $fail('You do not have permission to change the organisation owner.');
                        }
                    }
                }
            ],
        ];

        // For updates, make all fields optional
        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            foreach ($rules as $field => $validators) {
                if ($field !== 'owner_id') { // Keep special validation for owner_id
                    $rules[$field] = array_merge(['sometimes'], $validators);
                }
            }

            // Add unique rule for name
            if ($this->has('name')) {
                $rules['name'][] = Rule::unique('organisations')->ignore($this->route('organisation'));
            }
        } else if ($this->isMethod('POST')) {
            // For creation, ensure name is unique
            $rules['name'][] = 'unique:organisations,name';
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'organisation name',
            'owner_id' => 'organisation owner',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The organisation name is required.',
            'name.unique' => 'An organisation with this name already exists.',
            'website.url' => 'The website must be a valid URL.',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if ($this->isMethod('POST') && !$this->route('organisation')) {
            return $this->user()->can('create', Organisation::class);
        }

        if ($organisation = $this->route('organisation')) {
            return $this->user()->can('update', $organisation);
        }

        return false;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name)
            ]);
        }

        if ($this->has('website') && $this->website) {
            // Ensure website has proper protocol
            if (!preg_match('~^(?:f|ht)tps?://~i', $this->website)) {
                $this->merge([
                    'website' => 'https://' . $this->website
                ]);
            }
        }
    }
}
