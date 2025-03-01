<?php

namespace App\Http\Requests;

use App\Models\Priority;
use Illuminate\Foundation\Http\FormRequest;

class PriorityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer'],
        ];
    }

    public function authorize(): bool
    {
        if ($this->isMethod('POST') && !$this->route('priority')) {
            return $this->user()->can('create', Priority::class);
        }

        if ($this->isMethod('POST') && $this->route('priority')) {
            return $this->user()->can('update', $this->route('priority'));
        }

        return false;
    }
}
