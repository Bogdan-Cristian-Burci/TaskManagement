<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachUserToProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $this->user()->can('manageUsers', $project);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'user_ids' => 'required|array',
            'user_ids.*' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($project) {
                    // Check if user is already attached to the project
                    if ($project->users->contains($value)) {
                        $fail('User is already attached to this project.');
                    }

                    // Check if user belongs to the project's organisation
                    $userOrganisations = \App\Models\User::find($value)->organisations()->pluck('organisations.id')->toArray();
                    if (!in_array($project->organisation_id, $userOrganisations)) {
                        $fail('User must be a member of the project\'s organisation.');
                    }
                },
            ],
            'role' => [
                'sometimes',
                'string',
                Rule::in(['manager', 'developer', 'member']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Please select at least one user to attach.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
        ];
    }
}
