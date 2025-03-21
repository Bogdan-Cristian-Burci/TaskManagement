<?php

namespace App\Http\Requests;

use App\Models\BoardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
class BoardTypeRequest extends FormRequest
{
    public function rules(): array
    {
        $rules =  [
            'name' => ['required','string', 'max:255'],
            'description' => ['nullable', 'string'],

            'sort_by' => ['sometimes', 'string', Rule::in([
                'id', 'name', 'is_active', 'created_at', 'updated_at'
            ])],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            // Make fields optional for updates
            foreach ($rules as &$rule) {
                $rule = array_filter($rule, fn($item) => $item !== 'required');
            }
        }

        return $rules;
    }

    public function authorize(): bool
    {
        // For store requests
        if ($this->isMethod('POST')) {
            return auth()->user()->hasPermission('create', BoardType::class);
        }

        // For update/delete requests
        if ($this->route('boardType')) {
            return auth()->user()->hasPermission('update', $this->route('boardType'));
        }

        return false;
    }
}
