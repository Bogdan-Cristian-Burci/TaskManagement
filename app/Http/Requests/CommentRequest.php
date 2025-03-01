<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class CommentRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'content' => ['required', 'string'],
            'parent_id' => ['nullable', 'exists:comments,id'],
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            $rules['content'] = ['sometimes', 'string'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            $task = Task::findOrFail($this->route('task')->id);
            return $this->user()->can('comment', $task);
        }

        if ($comment = $this->route('comment')) {
            return $this->user()->can('update', $comment);
        }

        return false;
    }
}
