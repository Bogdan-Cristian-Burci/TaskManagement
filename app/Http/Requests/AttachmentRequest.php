<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $filename
 * @property string $original_filename
 * @property string $file_path
 * @property int $file_size
 * @property string $file_type
 * @property string $description
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
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For store requests, check if user can update the task
        if ($this->isMethod('POST')) {
            $taskId = $this->input('task_id');
            $task = Task::find($taskId);

            if (!$task) {
                return false;
            }

            return $this->user()->hasPermission('attachment.create', $task->project->organisation_id);
        }

        // For update/delete requests, check if user can update the attachment
        $attachment = $this->route('attachment');

        if (!$attachment) {
            return true; // Let the controller handle missing resources
        }

        return $this->user()->can('update', $attachment) ;
    }
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maxFileSize = config('attachments.max_file_size', 10240); // 10MB default

        if ($this->isMethod('POST')) {
            return [
                'task_id' => [
                    'required',
                    'exists:tasks,id',
                ],
                'file' => [
                    'required',
                    'file',
                    'max:' . $maxFileSize,
                    function ($attribute, $value, $fail) {
                        if (!in_array($value->getMimeType(), self::ALLOWED_MIME_TYPES)) {
                            $fail('The file must be of type: ' . implode(', ', $this->getAllowedExtensions()));
                        }
                    },
                ],
                'description' => ['nullable', 'string', 'max:255'],
            ];
        } else if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return [
                'description' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The file size cannot exceed ' .
                (config('attachments.max_file_size', 10240) / 1024) . 'MB.',
            'task_id.exists' => 'The selected task does not exist.',
        ];
    }

    /**
     * Get the file extensions allowed based on MIME types.
     *
     * @return array<string>
     */
    protected function getAllowedExtensions(): array
    {
        $extensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'text/plain' => ['txt'],
            'text/csv' => ['csv'],
            'text/markdown' => ['md'],
        ];

        $result = [];
        foreach (self::ALLOWED_MIME_TYPES as $mime) {
            if (isset($extensions[$mime])) {
                $result = array_merge($result, $extensions[$mime]);
            }
        }

        return array_unique($result);
    }
}
