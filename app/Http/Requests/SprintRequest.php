<?php

namespace App\Http\Requests;

use App\Enums\SprintStatusEnum;
use App\Models\Board;
use App\Models\Sprint;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SprintRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sprintId = $this->route('sprint') ? $this->route('sprint')->id : null;
        $isPostRequest = $this->isMethod('post');

        return [
            'name' => [
                $isPostRequest ? 'required' : 'sometimes',
                'string',
                'max:100',
                // Ensure sprint name is unique within the board
                Rule::unique('sprints')
                    ->where('board_id', $this->input('board_id'))
                    ->ignore($sprintId)
            ],
            'start_date' => [
                $isPostRequest ? 'required' : 'sometimes',
                'date',
                function ($attribute, $value, $fail) {
                    // If updating an existing sprint and the sprint is active or completed,
                    // don't allow changing the start date
                    if ($this->route('sprint') &&
                        in_array($this->route('sprint')->status, ['active', 'completed']) &&
                        $this->route('sprint')->start_date->format('Y-m-d') !== Carbon::parse($value)->format('Y-m-d')) {
                        $fail('Cannot change start date of an active or completed sprint.');
                    }
                }
            ],
            'end_date' => [
                $isPostRequest ? 'required' : 'sometimes',
                'date',
                'after_or_equal:start_date',
            ],
            'board_id' => [
                $isPostRequest ? 'required' : 'sometimes',
                'exists:boards,id',
                function ($attribute, $value, $fail) {
                    // Check if user has access to this board
                    $board = Board::find($value);
                    if (!$board || !$this->user()->can('view', $board)) {
                        $fail('You do not have access to this board.');
                    }
                    
                    // Set organisation_id if not provided
                    if (!$this->has('organisation_id') && $board && $board->project) {
                        $this->merge(['organisation_id' => $board->project->organisation_id]);
                    }
                }
            ],
            'goal' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::in(SprintStatusEnum::values()),
                function ($attribute, $value, $fail) {
                    // For existing sprints, validate status transitions
                    if ($this->route('sprint')) {
                        $currentStatus = $this->route('sprint')->status;
                        $newStatus = SprintStatusEnum::from($value);

                        if (!$currentStatus->canTransitionTo($newStatus)) {
                            $fail("Cannot change status from '{$currentStatus->value}' to '{$value}'.");
                        }
                    }
                },
            ],
            'organisation_id' => [
                'sometimes',
                'exists:organisations,id',
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if ($this->route('sprint')) {
            // For updating an existing sprint
            return $this->user()-> can('update', $this->route('sprint'));
        } else {
            // For creating a new sprint
            return $this->user()->can('create', ['App\Models\Sprint', $this->input('board_id')]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'board_id' => 'board',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A sprint with this name already exists on this board.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Convert dates to proper format
        if ($this->has('start_date') && !empty($this->start_date)) {
            $this->merge([
                'start_date' => Carbon::parse($this->start_date)->format('Y-m-d'),
            ]);
        }

        if ($this->has('end_date') && !empty($this->end_date)) {
            $this->merge([
                'end_date' => Carbon::parse($this->end_date)->format('Y-m-d'),
            ]);
        }
    }
}
