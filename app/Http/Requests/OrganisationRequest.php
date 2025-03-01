<?php

namespace App\Http\Requests;

use App\Models\Organisation;
use Illuminate\Foundation\Http\FormRequest;

class OrganisationRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'owner_id' => ['nullable', 'exists:users,id'],
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT') || $this->isMethod('POST') && $this->route('organisation')) {
            foreach ($rules as $field => $validators) {
                $rules[$field] = array_merge(['sometimes'], $validators);
            }
        }

        return $rules;
    }

    public function authorize(): bool
    {
        if ($this->isMethod('POST') && !$this->route('organisation')) {
            return $this->user()->can('create', Organisation::class);
        }

        if ($organisation = $this->route('organisation')) {
            return $this->user()->can('update', $organisation);
        }

        return false;
    }

}
