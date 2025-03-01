<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DetachUserFromTeamRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ];
    }

    public function authorize(): bool
    {
        $user = auth()->user();
        $team = $this->route('team');

        return $user->id === $team->team_lead_id;
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        $team = $this->route('team');

        $validator = Validator::make($validated, []);

        $validated['user_ids'] = array_filter($validated['user_ids'], function($userId) use ($team, $validator) {
            if ($team->team_lead_id == $userId) {
                $validator->errors()->add('user_ids', 'Team Lead cannot be deleted from the team, please change it before.');
                return false;
            }
            return true;
        });

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        return $validated;
    }
}
