<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DetachUserFromProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $this->user()->hasPermission('manage-projects', $project->organisation_id);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Remove 'user_id' requirement since it comes from the route
            'reassign_tasks' => 'sometimes|boolean',
            'reassign_to_user_id' => 'required_if:reassign_tasks,true|exists:users,id',
        ];
    }
}
