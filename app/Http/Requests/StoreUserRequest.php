<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'organisation_id' => 'nullable|integer|exists:organisations,id',
            'avatar' => 'nullable|string|max:1024',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'job_title' => 'nullable|string|max:100',
            'role' => 'nullable|string|exists:role_templates,name', // Changed to check role_templates instead of roles
            'organisation_role' => 'nullable|string|in:owner,admin,member',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Use hasPermission directly instead of can()
        return $this->user() && $this->user()->hasPermission('user.create', $this->user()->organisation_id);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'role' => 'user role template',
            'organisation_id' => 'organization',
            'organisation_role' => 'organization role',
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
            'email.unique' => 'This email address is already in use.',
            'password.confirmed' => 'The password confirmation does not match.',
            'organisation_role.in' => 'The organization role must be owner, admin, or member.',
            'role.exists' => 'The selected role template does not exist.',
        ];
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

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email))
            ]);
        }

        // Auto-fill organisation_id if not provided and user belongs to only one organisation
        if (!$this->filled('organisation_id') && $this->user()) {
            $userOrganisationsCount = $this->user()->organisations()->count();
            if ($userOrganisationsCount === 1) {
                $organisation = $this->user()->organisations()->first();
                $this->merge([
                    'organisation_id' => $organisation->id
                ]);
            }
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->filled('organisation_id') && $this->user()->organisations()->count() > 1) {
                $validator->errors()->add('organisation_id', 'Please select an organization for the new user.');
            }
        });
    }
}
