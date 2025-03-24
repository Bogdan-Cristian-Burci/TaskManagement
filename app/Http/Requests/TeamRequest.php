<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class TeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                $this->getUniqueRule()
            ],
            'description' => ['nullable', 'string'],
            'team_lead_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value && $this->isMethod('PUT') || $this->isMethod('PATCH')) {
                        // For team updates, ensure new team lead is a member
                        $team = $this->route('team');
                        if ($team && !$team->hasMember($value)) {
                            $fail('The new team lead must be a member of the team.');
                        }
                    }
                }
            ],
        ];

        // Make organisation_id required if user doesn't have a default organization
        if (!$this->user()->organisation_id) {
            $rules['organisation_id'] = ['required', 'exists:organisations,id'];
        } else {
            $rules['organisation_id'] = ['sometimes', 'exists:organisations,id'];
        }

        // For updates, make fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'][0] = 'sometimes';
        }

        return $rules;
    }

    /**
     * Get the unique rule for team name validation.
     *
     * @return Unique
     */
    protected function getUniqueRule() : Unique
    {

        $rule = Rule::unique('teams', 'name')
            ->where('organisation_id', $this->getOrganisationId());

        if ($this->route('team')) {
            $rule->ignore($this->route('team')->id);
        }

        return $rule;
    }

    /**
     * Get the organisation ID to use for validation.
     *
     * @return int|null
     */
    protected function getOrganisationId(): ?int
    {
        if ($this->has('organisation_id')) {
            return $this->input('organisation_id');
        }

        if ($this->route('team')) {
            return $this->route('team')->organisation_id;
        }

        $user = $this->user();
        \Log::info('User organisation id is: ' . $user->organisation_id);
        return $user->organisation_id;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            $organisationId = $this->input('organisation_id', $this->user()->organisation_id);

            if (!$organisationId) {
                return false;
            }

            return $this->user()->hasPermission('team.create', $organisationId);
        }

        if ($team = $this->route('team')) {
            return $this->user()->hasPermission('team.update', $team->organisation_id);
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
        if ($this->isMethod('POST')) {
            $data = [];

            // Set the organisation ID if not provided
            if (!$this->has('organisation_id')) {
                $user = $this->user();

                // Only set organisation_id if user has a default one
                if ($user->organisation_id) {
                    $data['organisation_id'] = $user->organisation_id;
                }
                // Otherwise, validation will catch the missing required field
            }

            // Set team lead ID if not provided
            if (!$this->has('team_lead_id')) {
                $data['team_lead_id'] = $this->user()->id;
            }

            if (!empty($data)) {
                $this->merge($data);
            }
        }

        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
    }

    /**
     * Get the validated data from the request.
     *
     * @param null $key
     * @param null $default
     * @return array
     */
    public function validated($key = null, $default = null) : array
    {
        $validated = parent::validated($key, $default);

        // Ensure these fields are included for new teams
        if ($this->isMethod('POST')) {
            // If organisation_id is not provided and user has a default one, use that
            if (!isset($validated['organisation_id'])) {
                $user = $this->user();

                if ($user->organisation_id) {
                    $validated['organisation_id'] = $user->organisation_id;
                }
                // Otherwise validation should have already failed
            }

            if (!isset($validated['team_lead_id'])) {
                $validated['team_lead_id'] = $this->user()->id;
            }
        }

        return $validated;
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
            if ($this->isMethod('POST') && !$this->user()->organisations()->exists()) {
                $validator->errors()->add('organisation_id', 'You must belong to at least one organisation to create a team.');
            }

            // Add explicit error if no organisation_id is available
            if ($this->isMethod('POST') && !$this->has('organisation_id') && !$this->user()->organisation_id) {
                $validator->errors()->add('organisation_id', 'The organisation_id field is required when you don\'t have a primary organisation.');
            }
        });
    }
}
