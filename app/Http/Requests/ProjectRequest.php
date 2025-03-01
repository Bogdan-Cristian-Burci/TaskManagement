<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use App\Models\Team;
use Illuminate\Support\Facades\Validator;

class ProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'organisation_id' => 'required|exists:organisations,id',
            'team_id' => 'required|exists:teams,id',
            'key' => 'nullable|string|unique:projects,key,' . $this->route('project.id'),
        ];
    }

    public function authorize(): bool
    {
        if ($this->isMethod('POST') && !$this->route('project')) {
            return $this->user()->can('create', Project::class);
        }

        if ($this->isMethod('POST') && $this->route('project')) {
            return $this->user()->can('update', $this->route('project'));
        }

        return false;
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        $data['organisations_id'] = auth()->user()->organisation->id;
        return $data;
    }
}
