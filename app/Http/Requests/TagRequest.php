<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Tag;
use App\Services\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
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
        $isUpdateOperation = $this->isMethod('PATCH') || $this->isMethod('PUT');

        // For update operations, ensure the tag isn't a system tag
        if ($isUpdateOperation && $tagId) {
            $tag = Tag::findOrFail($tagId);
            if ($tag->is_system) {
                // We'll handle this in the authorize() method
                // No need to add rules since we'll block the request
                return [];
            }
        }

        // Base rules - will be modified based on request type
        $rules = [
            'name' => [
                'string',
                'max:50',
            ],
            'color' => [
                'string',
                'regex:/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', // Validate hex color with or without # prefix
            ]
        ];

        // Only require these fields for create (POST) operations
        if (!$isUpdateOperation) {
            $rules['name'][] = 'required';
            $rules['color'][] = 'required';

            $rules['project_id'] = [
                'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    // Check if user has access to this project
                    $project = Project::find($value);
                    if (!$project || !$this->user()->hasPermission('project.view', $project->organisation_id)) {
                        $fail('You do not have access to this project.');
                    }
                }
            ];

            $rules['organisation_id'] = [
                'required',
                'exists:organisations,id',
            ];

            // Prevent creating system tags via API
            $rules['is_system'] = ['prohibited'];
        } else {
            // For update operations, make project_id optional but still validated if present
            $rules['project_id'] = [
                'sometimes',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    // Check if user has access to this project
                    $project = Project::find($value);
                    if (!$project || !$this->user()->hasPermission('project.view', $project->organisation_id)) {
                        $fail('You do not have access to this project.');
                    }
                }
            ];

            $rules['organisation_id'] = [
                'sometimes',
                'exists:organisations,id',
            ];

            // Prevent modifying is_system flag
            $rules['is_system'] = ['prohibited'];
        }

        // Add uniqueness constraint for name
        if ($this->has('name')) {
            // For uniqueness, we need the project_id
            $projectId = $this->input('project_id');

            // For update operations, if project_id isn't provided, get it from the existing tag
            if ($isUpdateOperation && !$projectId && $tagId) {
                $tag = Tag::find($tagId);
                if ($tag) {
                    $projectId = $tag->project_id;
                }
            }

            if ($projectId) {
                $rules['name'][] = Rule::unique('tags')
                    ->where('project_id', $projectId)
                    ->ignore($tagId);
            }
        }

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Block any attempts to update system tags
        $tagId = $this->route('tag') ? $this->route('tag')->id : null;
        $isUpdateOperation = $this->isMethod('PATCH') || $this->isMethod('PUT');

        if ($isUpdateOperation && $tagId) {
            $tag = Tag::findOrFail($tagId);
            if ($tag->is_system) {
                return false; // Reject any updates to system tags
            }
        }

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

        // Only add organisation_id for new tags or if explicitly updating it
        $isUpdateOperation = $this->isMethod('PATCH') || $this->isMethod('PUT');
        if ($organisationId && (!$isUpdateOperation || $this->has('organisation_id'))) {
            $this->merge([
                'organisation_id' => $organisationId,
            ]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_system.prohibited' => 'System tag properties cannot be modified.',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('System tags cannot be modified.');
    }
}
