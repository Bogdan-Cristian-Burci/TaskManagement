<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DetachUserFromProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $this->user()->hasPermission('manageUsers', $project);
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($project) {
                    // Check if user is attached to the project
                    if (!$project->users->contains($value)) {
                        $fail('User is not attached to this project.');
                    }

                    // Check if we're trying to remove ourselves
                    if ($value == auth()->id()) {
                        $fail('You cannot remove yourself from the project.');
                    }

                    // Check if this would leave the project without any managers
                    $userToRemove = User::find($value);
                    $isManager = $project->users()
                        ->where('users.id', $value)
                        ->wherePivot('role', 'manager')
                        ->exists();

                    $managerCount = $project->users()
                        ->wherePivot('role', 'manager')
                        ->count();

                    if ($isManager && $managerCount <= 1) {
                        $fail('Cannot remove the last project manager. Please assign a new manager first.');
                    }
                },
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
            'user_id.required' => 'Please select a user to remove.',
            'user_id.exists' => 'The selected user does not exist.',
        ];
    }
}
