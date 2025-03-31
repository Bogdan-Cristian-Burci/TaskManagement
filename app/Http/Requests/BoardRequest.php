<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->route('board')) {
            return $this->user()->hasPermission('board.update');
        }

        return $this->user()->hasPermission('board.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $boardId = $this->route('board') ? $this->route('board')->id : null;

        return [
            'name' => [
                $this->isMethod('patch') ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('boards')
                    ->where('project_id', $this->input('project_id'))
                    ->ignore($boardId)
            ],
            'description' => ['nullable', 'string'],
            'project_id' => [
                $this->isMethod('patch') ? 'sometimes' : 'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    if (!$this->user()->projects()->where('projects.id', $value)->exists()) {
                        $fail('You do not have access to this project.');
                    }
                }
            ],
            'board_type_id' => [$this->isMethod('patch') ? 'sometimes' : 'required', 'exists:board_types,id'],
        ];
    }
}
