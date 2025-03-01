<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Auth\Access\AuthorizationException;

class AttachUserToProjectRequest extends FormRequest
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
        return $this->user()->can('manageUsers', $this->route('project'));
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $project = $this->route('project');
            $existingUserIds = $project->users()->pluck('users.id')->toArray();
            $duplicateUserIds = array_intersect($this->input('user_ids'), $existingUserIds);

            if (!empty($duplicateUserIds)) {
                $validator->errors()->add('user_ids', 'Some users are already attached to this project.');
            }
        });
    }
}
