<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TagRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'color' => ['required'],
            'projects_id' => ['required', 'exists:projects'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
