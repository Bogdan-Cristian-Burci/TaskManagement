<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Tag;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tagId = $this->route('tag') ? $this->route('tag')->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                // Ensure tag name is unique within the project
                Rule::unique('tags')
                    ->where('project_id', $this->input('project_id'))
                    ->ignore($tagId)
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', // Validate hex color with or without # prefix
            ],
            'project_id' => [
                'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    // Check if user has access to this project
                    $project = Project::find($value);
                    if (!$project || !$this->user()->hasPermission('project.view', $project->organisation_id)) {
                        $fail('You do not have access to this project.');
                    }
                }
            ],
            'organisation_id' => [
                'required',
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
        return true;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'project_id' => 'project',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Ensure color is properly formatted (add # if missing)
        if ($this->has('color') && !str_starts_with($this->color, '#')) {
            $this->merge([
                'color' => "#{$this->color}",
            ]);
        }

        $organisationId = OrganizationContext::getCurrentOrganizationId();

        if ($organisationId) {
            $this->merge([
                'organisation_id' => $organisationId,
            ]);
        }
    }
}
