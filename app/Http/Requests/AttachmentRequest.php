<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use App\Models\Task;
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
    /**
     * List of allowed MIME types
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv', 'text/markdown'
    ];

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maxFileSize = config('attachments.max_file_size', 10485760); // 10MB default

        $rules = [
            'file_name' => ['required', 'string', 'max:255'],
            'file_url' => ['required', 'string', 'url'],
            'file_size' => ['required', 'integer', 'max:' . $maxFileSize],
            'mime_type' => [
                'required',
                'string',
                'regex:/^[\w\-\.]+\/[\w\-\.]+$/',
                Rule::in(self::ALLOWED_MIME_TYPES)
            ],
            'task_id' => [
                'required',
                'exists:tasks,id',
                function ($attribute, $value, $fail) {
                    $task = Task::find($value);
                    if ($task && !$this->user()->can('view', $task)) {
                        $fail('You do not have permission to attach files to this task.');
                    }
                }
            ],
            'user_id' => ['sometimes', 'exists:users,id'],

            'sort_by' => ['sometimes', 'string', Rule::in([
                'id', 'file_name', 'file_size', 'mime_type', 'created_at', 'updated_at'
            ])],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            // Make fields optional for updates
            foreach (['file_name', 'file_url', 'file_size', 'mime_type', 'task_id'] as $field) {
                if (isset($rules[$field])) {
                    $rules[$field] = array_merge(['sometimes'], array_filter($rules[$field],
                        fn($item) => $item !== 'required'));
                }
            }
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('user_id') && $this->isMethod('POST')) {
            $this->merge([
                'user_id' => $this->user()->id,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For store requests, check if user can create attachments
        if ($this->isMethod('POST')) {
            return $this->user()->can('create', Attachment::class);
        }

        // For update/delete requests, check if user can update this specific attachment
        if ($this->route('attachment')) {
            return $this->user()->can('update', $this->route('attachment'));
        }

        return false;
    }

    /**
     * Get custom error messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'file_name.required' => 'A file name is required',
            'file_url.url' => 'The file URL must be a valid URL',
            'file_size.max' => 'The file size cannot exceed ' .
                $this->formatBytes(config('attachments.max_file_size', 10485760)),
            'mime_type.regex' => 'The file type format is invalid',
            'mime_type.in' => 'The file type is not allowed. Allowed types: ' .
                implode(', ', array_map(fn($type) => explode('/', $type)[1] ?? $type, self::ALLOWED_MIME_TYPES)),
            'task_id.exists' => 'The selected task does not exist',
        ];
    }

    /**
     * Format bytes to human-readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
