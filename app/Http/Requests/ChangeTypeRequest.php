<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeTypeRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            foreach ($rules as &$rule) {
                $rule = array_filter($rule, fn($item) => $item !== 'required');
            }
        }

        return $rules;
    }

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return auth()->user()->can('create', ChangeType::class);
        }

        if ($this->route('changeType')) {
            return auth()->user()->can('update', $this->route('changeType'));
        }

        return false;
    }
}
