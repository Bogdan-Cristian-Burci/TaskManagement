<?php

namespace App\Http\Requests;

use App\Models\Comment;
use App\Models\Task;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'content' => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => [
                'nullable',
                'exists:comments,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $parentComment = Comment::find($value);
                        // Ensure the parent comment is associated with the same task
                        if ($parentComment && $parentComment->task_id != $this->route('task')->id) {
                            $fail('The parent comment must belong to the same task.');
                        }

                        // Prevent nested replies beyond one level
                        if ($parentComment && $parentComment->parent_id !== null) {
                            $fail('Nested replies are limited to one level.');
                        }
                    }
                }
            ],
            'metadata' => ['nullable', 'array'],
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            $rules['content'] = ['sometimes', 'required', 'string', 'min:1', 'max:5000'];

            // Check if the time window for editing has passed
            $comment = $this->route('comment');
            if ($comment && $comment->user_id === $this->user()->id) {
                if ($comment->created_at->diffInMinutes(now()) > 30) {
                    $rules['_edit_time_expired'] = ['required', function ($attribute, $value, $fail) {
                        $fail('The time window for editing this comment has expired.');
                    }];
                }
            }
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'content' => 'comment content',
            'parent_id' => 'parent comment',
            'metadata' => 'metadata',
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
            'content.required' => 'The comment cannot be empty.',
            'content.min' => 'The comment must contain at least :min character.',
            'content.max' => 'The comment cannot exceed :max characters.',
            'parent_id.exists' => 'The selected parent comment does not exist.',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {

            $task = $this->route('task');

            // First check: User has general permission to create comments
            $organisationId = OrganizationContext::getCurrentOrganizationId();

            if (!$this->user()->hasPermission('comment.create', $organisationId)) {
                return false;
            }

            // Second check: User belongs to the project (or is admin)
            $projectId = $task->project_id;
            return $this->user()
                ->projects()
                ->where('projects.id', $projectId)
                ->exists();
        }

        if ($comment = $this->route('comment')) {
            // For updates, check if user owns comment or has update permission
            if ($comment->user_id === $this->user()->id) {
                return true; // Comment author can edit their own comment
            }

            // Otherwise, check update permission at organization level
            $organisationId = OrganizationContext::getCurrentOrganizationId();
            return $this->user()->hasPermission('comment.update', $organisationId);
        }

        return false;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $content = $this->input('content');

        $this->merge(['content' => trim($content)]);
    }
}
