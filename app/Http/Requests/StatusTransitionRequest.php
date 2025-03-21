<?php

namespace App\Http\Requests;

use App\Models\Status;
use App\Models\StatusTransition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusTransitionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage', Status::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'from_status_id' => ['required', 'integer', 'exists:statuses,id'],
            'to_status_id' => ['required', 'integer', 'exists:statuses,id', 'different:from_status_id'],
            'board_id' => ['nullable', 'integer', 'exists:boards,id'],
        ];

        // Prevent duplicate transitions
        if ($this->isMethod('POST')) {
            $rules['to_status_id'][] = function ($attribute, $value, $fail) {
                $exists = StatusTransition::where('from_status_id', $this->from_status_id)
                    ->where('to_status_id', $value)
                    ->where('board_id', $this->board_id)
                    ->exists();

                if ($exists) {
                    $fail('This status transition already exists.');
                }
            };
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_status_id.required' => 'The source status is required.',
            'to_status_id.required' => 'The target status is required.',
            'to_status_id.different' => 'The target status must be different from the source status.',
            'from_status_id.exists' => 'The selected source status does not exist.',
            'to_status_id.exists' => 'The selected target status does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Generate a default name if not provided
        if (!$this->has('name') && $this->has('from_status_id') && $this->has('to_status_id')) {
            $fromStatus = Status::find($this->from_status_id);
            $toStatus = Status::find($this->to_status_id);

            if ($fromStatus && $toStatus) {
                $this->merge([
                    'name' => "{$fromStatus->name} to {$toStatus->name}"
                ]);
            }
        }
    }
}
