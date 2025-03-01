<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BoardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required','string', 'max:255'],
            'description' => ['required','string','max:255'],
            'type' => ['sometimes','string','max:255'],
            'project_id' => ['required', 'exists:projects,id'],
            'board_type_id' => ['sometimes','nullable','exists:board_types,id'],
        ];
    }

    public function authorize(): bool
    {
        if ($this->isMethod('POST') && !$this->route('board')) {
            return $this->user()->can('create board');
        }

        if ($this->isMethod('POST') && $this->route('board')) {
            return $this->user()->can('update board');
        }

        return false;
    }
}
