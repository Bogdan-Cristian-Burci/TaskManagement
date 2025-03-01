<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'description' => ['required'],
            'project_id' => ['required', 'exists:projects'],
            'board_id' => ['required', 'exists:boards'],
            'status_id' => ['required', 'exists:statuses'],
            'priority_id' => ['required', 'exists:priorities'],
            'task_type_id' => ['required', 'exists:task_types'],
            'responsible_id' => ['required', 'exists:users'],
            'reporter_id' => ['required', 'exists:users'],
            'task_number' => ['required', 'integer'],
            'parent_task_id' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
