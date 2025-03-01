<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SprintRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'board_id' => ['required', 'exists:boards'],
            'goal' => ['required'],
            'status_id' => ['required', 'exists:statuses'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
