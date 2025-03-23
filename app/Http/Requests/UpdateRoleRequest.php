<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Role;
use App\Models\RoleTemplate;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('role.update', $this->user()->organisation_id);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $organisationId = $this->user()->organisation_id;

        return [
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'level' => 'sometimes|nullable|integer|min:1|max:100',
            'permissions' => 'sometimes|nullable|array',
            'permissions.*' => 'sometimes|string|exists:permissions,name',
        ];
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    public function validationData(): array
    {
        // First try to get data from standard request
        $data = parent::validationData();

        // If data is empty, try to parse JSON directly from content
        if (empty($data) && $this->getContent()) {
            $jsonData = json_decode($this->getContent(), true);

            // Only use if valid JSON was decoded
            if (is_array($jsonData) && json_last_error() === JSON_ERROR_NONE) {
                $data = $jsonData;
            }
        }

        Log::debug('Final validation data', [
            'data' => $data,
            'content_type' => $this->header('Content-Type')
        ]);

        return $data;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roleId = $this->route('role'); // Changed from 'id' to 'role'
            $organisationId = $this->user()->organisation_id;

            // Rest of validation logic...
        });
    }
}
