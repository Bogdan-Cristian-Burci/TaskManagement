<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Team;
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

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'organisation_id' => [
                'required',
                'exists:organisations,id',
                function ($attribute, $value, $fail) {
                    // Check if the user belongs to this organisation
                    $userOrganisations = Auth::user()->organisations()->pluck('organisations.id')->toArray();
                    if (!in_array($value, $userOrganisations)) {
                        $fail('You can only create projects for organisations you belong to.');
                    }
                },
            ],
            'team_id' => [
                'required',
                'exists:teams,id',
                function ($attribute, $value, $fail) {
                    // Check if the team belongs to the specified organisation
                    $team = Team::find($value);
                    if (!$team || $team->organisation_id != $this->input('organisation_id')) {
                        $fail('The team must belong to the specified organisation.');
                    }

                    // Check if the user is a member of this team
                    if (!Auth::user()->teams->contains($value)) {
                        $fail('You can only create projects for teams you belong to.');
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
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if ($this->isMethod('POST') && !$this->route('project')) {
            return $this->user()->hasPermission('create', Project::class);
        }

        if (($this->isMethod('PUT') || $this->isMethod('PATCH')) && $this->route('project')) {
            return $this->user()->hasPermission('update', $this->route('project'));
        }

        return false;
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
        ];
    }

    /**
     * Get the validated data from the request.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        // Ensure the organisation ID is correct if not explicitly provided
        if (!isset($data['organisation_id'])) {
            $data['organisation_id'] = Auth::user()->organisation_id;
        }

        return $data;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Force key to uppercase
        if ($this->has('key')) {
            $this->merge([
                'key' => strtoupper($this->key)
            ]);
        }
    }
}
