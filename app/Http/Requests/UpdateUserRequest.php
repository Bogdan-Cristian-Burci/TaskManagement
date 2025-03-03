<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user)
            ],
            'password' => ['sometimes', 'string', 'confirmed', Password::defaults()],
            'avatar' => 'nullable|string|max:1024',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'job_title' => 'nullable|string|max:100',
            'role' => 'nullable|string|exists:roles,name',
            'organisation_id' => 'nullable|exists:organisations,id',
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
        if (!$this->user && !$this->route('user')) {
            return false;
        }

        $user = $this->user ?? $this->route('user');
        return $this->user()->can('update', $user);
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
            'role' => 'user role',
            'organisation_id' => 'organization',
            'organisation_role' => 'organization role',
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
    }
}
