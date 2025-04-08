<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\Models\Task;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;

class TaskTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $task = $this->route('task');
        return $this->user()->can('update', $task);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'tag_ids' => 'required|array',
            'tag_ids.*' => [
                'integer',
                'exists:tags,id',
                function ($attribute, $value, $fail) use ($task) {
                    $tag = Tag::find($value);

                    if (!$tag) {
                        return $fail('Tag not found.');
                    }

                    // Allow system tags from the task's organization
                    if ($tag->is_system) {
                        return;
                    }

                    // For regular tags, ensure they belong to the same project as the task
                    if ($tag->project_id !== $task->project_id) {
                        return $fail('Tag must belong to the same project as the task.');
                    }
                }
            ],
            'replace' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tag_ids.required' => 'Please provide at least one tag to assign.',
            'tag_ids.array' => 'Tags must be provided as an array of IDs.',
            'tag_ids.*.exists' => 'One or more tags do not exist.',
        ];
    }
}
