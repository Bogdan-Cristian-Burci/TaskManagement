<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    // Check if the operation would affect the team lead
                    if ($this->route('team') &&
                        $this->route('team')->team_lead_id === (int)$value &&
                        $this->routeIs('*.removeMembers')) {
                        $fail('Cannot remove the team lead. Assign a new team lead first.');
                    }
                }
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if ($team = $this->route('team')) {
            return $this->user()->hasPermission('manageMembers', $team);
        }

        return false;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'At least one user must be selected.',
            'user_ids.array' => 'User IDs must be provided as an array.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Convert string user_ids to array if needed (useful for form submissions)
        if ($this->has('user_ids') && is_string($this->input('user_ids'))) {
            $userIds = explode(',', $this->input('user_ids'));
            $userIds = array_map('trim', $userIds);
            $userIds = array_filter($userIds);
            $this->merge(['user_ids' => $userIds]);
        }
    }
}
