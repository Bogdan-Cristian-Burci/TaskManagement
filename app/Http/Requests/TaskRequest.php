<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class TaskRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'project_id' => ['required', 'exists:projects,id'],
            'board_id' => ['required', 'exists:boards,id'],
            'board_column_id' => ['required', 'exists:board_columns,id'],
            'status_id' => ['required', 'exists:statuses,id'],
            'priority_id' => ['required', 'exists:priorities,id'],
            'task_type_id' => ['required', 'exists:task_types,id'],
            'responsible_id' => ['required', 'exists:users,id'],
            'reporter_id' => ['required', 'exists:users,id'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'spent_hours' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];

        // Only make task_number required on update, as it's auto-generated on create
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['task_number'] = ['required', 'integer'];
        } else {
            $rules['task_number'] = ['nullable', 'integer'];
        }

        // Parent task validation - must exist and not create circular references
        $rules['parent_task_id'] = [
            'nullable',
            'exists:tasks,id',
            function ($attribute, $value, $fail) {
                if ($this->route('task') && $value == $this->route('task')->id) {
                    $fail('A task cannot be its own parent.');
                }
            }
        ];

        return $rules;
    }

    public function authorize(): bool
    {
        $task = $this->route('task');

        if (!$task) {
            return $this->user()->hasPermission('create', Task::class);
        }

        // For updates/deletes, use the TaskPolicy
        return $this->user()->hasPermission('update', $task);
    }

    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'The due date must be after or equal to the start date.',
            'parent_task_id.exists' => 'The selected parent task does not exist.',
        ];
    }
}
