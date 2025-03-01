<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskHistoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'old_value' => ['required'],
            'new_value' => ['required'],
            'task_id' => ['required', 'exists:tasks'],
            'changed_by' => ['required', 'exists:users'],
            'change_type_id' => ['required', 'exists:change_types'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
