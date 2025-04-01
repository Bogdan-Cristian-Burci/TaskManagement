<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BoardTypeRequest extends FormRequest
{
    public function authorize(): true
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];

        // For store (create) operations, template_id is required
        if ($this->isMethod('post')) {
            $rules['template_id'] = 'required|exists:board_templates,id';
        }
        // For update operations, make template_id optional but valid if provided
        else if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['template_id'] = 'sometimes|exists:board_templates,id';
        }

        return $rules;
    }
}
