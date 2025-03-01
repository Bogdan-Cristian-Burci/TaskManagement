<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class DetachUserFromProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ];
    }

    // Check if the authenticated user is the team lead of the project to exclude users from project,
    // or the request was sent by the user itself
    public function authorize(): bool
    {

        $project = $this->route('project');
        $userIds = $this->input('user_ids', []);

        // Allow users to remove only themselves
        if (count($userIds) === 1 && (int)$userIds[0] === auth()->id()) {
            return true;
        }

        // Otherwise check if user has permission to manage project users
        return $this->user()->can('manageUsers', $project);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $project = $this->route('project');
            $userIds = $this->input('user_ids', []);
            $projectUserIds = $project->users()->pluck('users.id')->toArray();

            // Check if all users to be detached are actually part of the project
            $nonProjectUsers = array_diff($userIds, $projectUserIds);

            if (!empty($nonProjectUsers)) {
                $validator->errors()->add(
                    'user_ids',
                    'Some users are not attached to this project.'
                );
            }
        });
    }
}
