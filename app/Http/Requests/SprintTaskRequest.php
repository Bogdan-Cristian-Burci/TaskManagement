<?php

namespace App\Http\Requests;

use App\Models\Sprint;
use Illuminate\Foundation\Http\FormRequest;

class SprintTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'required|exists:tasks,id',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $sprint = $this->route('sprint');
        return $this->user() && $this->user()->hasPermission('manage-projects', $sprint->organisation);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_ids.required' => 'Please select at least one task.',
            'task_ids.*.exists' => 'One or more selected tasks do not exist.',
        ];
    }
}
