<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $file_name
 * @property string $file_url
 * @property int $file_size
 * @property string $mime_type
 * @property int $task_id
 * @property int $user_id
 */
class AttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'file_name' => ['required', 'string', 'max:255'],
            'file_url' => ['required', 'string','url'],
            'file_size' => ['required', 'integer','max:10485760'],
            'mime_type' => ['required', 'string','regex:/^[\w\-\.]+\/[\w\-\.]+$/',
                Rule::in([
                'image/jpeg', 'image/png', 'image/gif',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain', 'text/csv'
            ])],
            'task_id' => ['required', 'exists:tasks,id'],
            'user_id' => ['required', 'exists:users,id'],

            'sort_by' => ['sometimes', 'string', Rule::in([
                'id', 'file_name', 'file_size', 'mime_type', 'created_at', 'updated_at'
            ])],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            // Make fields optional for updates
            foreach ($rules as &$rule) {
                $rule = array_filter($rule, fn($item) => $item !== 'required');
            }
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('user_id')) {
            $this->merge([
                'user_id' => auth()->id(),
            ]);
        }
    }

    public function authorize(): bool
    {
        // For store requests, check if user can create attachments
        if ($this->isMethod('POST')) {
            return auth()->user()->can('create', Attachment::class);
        }

        // For update/delete requests, check if user can update this specific attachment
        if ($this->route('attachment')) {
            return auth()->user()->can('update', $this->route('attachment'));
        }

        return false;
    }

    public function messages(): array
    {
        return [
            'file_name.required' => 'A file name is required',
            'file_url.url' => 'The file URL must be a valid URL',
            'file_size.max' => 'The file size cannot exceed 10MB',
            'mime_type.regex' => 'The file type format is invalid',
            'task_id.exists' => 'The selected task does not exist',
        ];
    }
}
