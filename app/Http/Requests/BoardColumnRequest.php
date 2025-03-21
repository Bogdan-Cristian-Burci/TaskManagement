<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $name
 * @property int $board_id
 * @property int $position
 * @property string $color
 * @property int $wip_limit
 */
class BoardColumnRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'board_id' => ['required', 'exists:boards,id'],
            'position' => ['required', 'integer', 'min:0'],
            'color' => ['sometimes', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,6}|[a-zA-Z]+)$/'],
            'wip_limit' => ['sometimes', 'integer', 'min:0'],

            // Sorting and pagination parameters
            'sort_by' => ['sometimes', 'string', Rule::in([
                'id', 'name', 'position', 'created_at', 'updated_at'
            ])],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            // Make fields optional for updates
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['board_id'] = ['sometimes', 'exists:boards,id'];
            $rules['position'] = ['sometimes', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return auth()->user()->hasPermission('create', BoardColumn::class);
        }

        // For update/delete requests, check if user can update this specific column
        if ($this->route('boardColumn')) {
            return auth()->user()->hasPermission('update', $this->route('boardColumn'));
        }

        return false;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Column name is required',
            'board_id.exists' => 'The selected board does not exist',
            'position.integer' => 'Position must be a number',
            'wip_limit.min' => 'WIP limit cannot be negative',
        ];
    }
}
