<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssignUsersToTeamRequest extends FormRequest
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

        foreach ($validated['user_ids'] as $userId) {
            if ($team->users()->where('users.id', $userId)->exists()) {
                $validator->errors()->add('user_ids', 'User ID ' . $userId . ' is already in the team.');
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }
        return $validated;
    }
}
