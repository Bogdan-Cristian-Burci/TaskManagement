<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $projectId = $this->route('project.id') ?? $this->route('project') ? $this->route('project')->id : null;

        // Base rules
        $rules = [
            'description' => 'nullable|string',
            'organisation_id' => [
                'sometimes', // Allow it to be submitted but not required
                'exists:organisations,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Check if the user belongs to this organisation
                        $userOrganisations = Auth::user()->organisations()->pluck('organisations.id')->toArray();
                        if (!in_array($value, $userOrganisations)) {
                            $fail('You can only create projects for organisations you belong to.');
                        }
                    }
                },
            ],
            'team_id' => [
                'nullable', // Make it nullable since we'll create a team if needed
                'exists:teams,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Get the organisation_id either from input or user
                        $organisationId = $this->input('organisation_id', Auth::user()->organisation_id);

                        // Check if the team belongs to the specified organisation
                        $team = Team::find($value);

                        if (!$team || $team->organisation_id != $organisationId) {
                            $fail('The team must belong to the specified organisation.');
                        }

                        // Check if the user is a member of this team
                        if (!Auth::user()->teams->contains($value)) {
                            $fail('You can only create projects for teams you belong to.');
                        }
                    }
                },
            ],
            'responsible_user_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Check if the user belongs to the organization
                        $user = User::find($value);
                        $organisationId = $this->input('organisation_id', Auth::user()->organisation_id);

                        if (!$user || !$user->organisations()->where('organisations.id', $organisationId)->exists()) {
                            $fail('The responsible user must belong to the specified organisation.');
                        }
                    }
                },
            ],
            'key' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+-[0-9]+$/',
                Rule::unique('projects')->ignore($projectId),
            ],
            'status' => ['sometimes', 'string', Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',

        ];

        // Adjust rules based on request method
        if ($this->isMethod('POST')) {
            // For creation (POST), name is required
            $rules['name'] = 'required|string|max:255';
            $rules['board_type_id'] = 'required|exists:board_types,id'; // For optional board creation
        } else {
            // For updates (PUT/PATCH), name is optional but validated if present
            $rules['name'] = 'sometimes|string|max:255';
            $rules['board_type_id'] = 'sometimes|exists:board_types,id'; // For optional board updates
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('POST')) {
            // Auto-populate organisation_id from the authenticated user if not provided
            if (!$this->has('organisation_id')) {
                $this->merge([
                    'organisation_id' => Auth::user()->organisation_id
                ]);
            }

            // Set current user as responsible_user if not provided
            if (!$this->has('responsible_user_id')) {
                $this->merge([
                    'responsible_user_id' => Auth::user()->id
                ]);
            }

            // Force key to uppercase if provided
            if ($this->has('key')) {
                $this->merge([
                    'key' => strtoupper($this->input('key'))
                ]);
            }
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'team_id' => 'team',
            'organisation_id' => 'organisation',
            'board_type_id' => 'board type',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required to create a project.',
            'organisation_id.exists' => 'The selected organization does not exist or you do not have access to it.',
            'team_id.exists' => 'The selected team does not exist.',
            'board_type_id.exists' => 'The selected board type does not exist.',
        ];
    }
}
