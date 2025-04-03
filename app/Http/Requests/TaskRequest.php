<?php

namespace App\Http\Requests;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\BoardType;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['string', 'max:255'],
            'description' => ['string'],
            'project_id' => ['exists:projects,id'],
            'priority_id' => ['exists:priorities,id'],
            'task_type_id' => ['exists:task_types,id'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'spent_hours' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'position' => ['nullable', 'integer', 'min:0'],
            'task_number' => ['string'],
        ];

        // Make board_id validation dependent on project_id
        $rules['board_id'] = [
            'exists:boards,id',
            function ($attribute, $value, $fail) {
                if (!$value && !$this->input('project_id')) {
                    $fail('Either board_id or project_id must be provided.');
                }

                if ($value && $this->input('project_id')) {
                    // Check that board belongs to project
                    $boardBelongsToProject = Board::where('id', $value)
                        ->where('project_id', $this->input('project_id'))
                        ->exists();

                    if (!$boardBelongsToProject) {
                        $fail('The selected board does not belong to the specified project.');
                    }
                }
            }
        ];

        // Validate board_column belongs to the board
        $rules['board_column_id'] = [
            'nullable', // Changed from required to nullable
            'exists:board_columns,id',
            function ($attribute, $value, $fail) {
                if ($value && $this->input('board_id')) {
                    $columnBelongsToBoard = BoardColumn::where('id', $value)
                        ->where('board_id', $this->input('board_id'))
                        ->exists();

                    if (!$columnBelongsToBoard) {
                        $fail('The selected board column does not belong to the specified board.');
                    }
                }
            }
        ];

        // Status can be defaulted if not provided
        $rules['status_id'] = [
            'nullable', // Changed from required to nullable
            'exists:statuses,id',
        ];

        // Make responsible_id and reporter_id nullable
        $rules['responsible_id'] = [
            'nullable', // Changed from required to nullable
            'exists:users,id',
            function ($attribute, $value, $fail) {
                if ($value && $this->input('project_id')) {
                    // Check if user is member of the project
                    $project = Project::find($this->input('project_id'));
                    $userInProject = $project && $project->users()->where('users.id', $value)->exists();

                    if (!$userInProject) {
                        $fail('The assigned user is not a member of the project.');
                    }
                }
            }
        ];

        $rules['reporter_id'] = [
            'nullable', // Changed from required to nullable
            'exists:users,id',
        ];

        // Parent task validation - must exist and not create circular references
        $rules['parent_task_id'] = [
            'nullable',
            'exists:tasks,id',
            function ($attribute, $value, $fail) {
                // Avoid self-reference
                if ($this->route('task') && $value == $this->route('task')->id) {
                    $fail('A task cannot be its own parent.');
                }

                // Validate parent task is in same project
                if ($value && $this->input('project_id')) {
                    $parentTask = Task::find($value);
                    if ($parentTask && $parentTask->project_id != $this->input('project_id')) {
                        $fail('The parent task must be in the same project.');
                    }
                }
            }
        ];

        if ($this->isMethod('post')) {
            $rules['name'][] = 'required';
            $rules['description'][] = 'required';
            $rules['project_id'][] = 'required';
            $rules['priority_id'][] = 'required';
            $rules['task_type_id'][] = 'required';
            $rules['task_number'][] = 'nullable';
            $rules['board_id'][] = 'required';
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     * @throws \Exception
     */
    protected function prepareForValidation(): void
    {
        // For new tasks, set reasonable defaults
        if ($this->isMethod('post')) {
            $this->mergeDefaults();
        }
    }

    /**
     * Set default values for certain fields if not provided
     *
     * @return void
     */
    protected function mergeDefaults(): void
    {
        $defaults = [];
        $projectId = $this->input('project_id');

        // Bail early if no project ID
        if (!$projectId) {
            return;
        }

        try {
            DB::beginTransaction();

            // Set default board_id if project_id is provided but board_id is not
            if (!$this->has('board_id')) {
                // First try to find the default board for this project
                $defaultBoard = Board::where('project_id', $projectId)
                    ->first();

                // If not found, just get the first board for this project
                if (!$defaultBoard) {
                    $defaultBoard = Board::where('project_id', $projectId)
                        ->orderBy('id')
                        ->first();
                }

                // If we found a board, use it
                if ($defaultBoard) {
                    $defaults['board_id'] = $defaultBoard->id;
                } else {
                    throw new \Exception('No board found for this project. Please check your database seeding.');
                }
            }

            // Set default board_column_id if board_id is available but board_column_id is not
            $boardId = $this->input('board_id') ?? $defaults['board_id'] ?? null;
            if ($boardId && !$this->has('board_column_id')) {
                $firstColumn = BoardColumn::where('board_id', $boardId)
                    ->orderBy('position')
                    ->first();

                if (!$firstColumn) {
                    throw new \Exception('The selected board has no columns. Please check your database seeding.');
                }

                $defaults['board_column_id'] = $firstColumn->id;
            }

            // Get a valid status ID - guaranteed method
            $statusId = null;

            // Try to find a status with is_default = true
            $defaultStatus = Status::where('is_default', true)->first();
            if ($defaultStatus) {
                $statusId = $defaultStatus->id;
            }

            // If not found, try category = todo
            if (!$statusId) {
                $todoStatus = Status::where('category', 'todo')->first();
                if ($todoStatus) {
                    $statusId = $todoStatus->id;
                }
            }

            // If still not found, get the first status
            if (!$statusId) {
                $anyStatus = Status::orderBy('id')->first();
                if ($anyStatus) {
                    $statusId = $anyStatus->id;
                }
            }

            // If we still don't have a status ID, it's a critical error
            if (!$statusId) {
                throw new \Exception('Cannot find or create a valid status ID');
            }

            // Set the status_id - directly override any existing value for consistency
            $this->merge(['status_id' => $statusId]);

            $userId = auth()->id();

            // Set current user as reporter and responsible if not provided
            if (!$this->has('reporter_id') || $this->input('reporter_id') === null) {
                $defaults['reporter_id'] = $userId;
            }

            if (!$this->has('responsible_id') || $this->input('responsible_id') === null) {
                $defaults['responsible_id'] = $userId;
            }

            // Generate task number if not provided
            if (!$this->has('task_number')) {
                $project = Project::findOrFail($projectId);
                $prefix = $project->code ?? 'TASK';

                // Get the last task number for this project
                $lastTask = Task::where('project_id', $projectId)
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNumber = 1;
                if ($lastTask && preg_match('/(\d+)/', $lastTask->task_number, $matches)) {
                    $nextNumber = (int)$matches[1] + 1;
                }

                $defaults['task_number'] = $prefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }

            // Merge defaults with request data
            if (!empty($defaults)) {
                $this->merge($defaults);
            }

            DB::commit();
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            DB::rollBack();
            throw $e;
        }
    }
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $task = $this->route('task');
        $projectId = $this->input('project_id');
        $organisationId = null;

        if ($projectId) {
            $project = Project::find($projectId);
            $organisationId = $project?->organisation_id;
        }
        if (!$task) {
            return $this->user()->hasPermission('task.create', $organisationId);
        }

        // For updates/deletes, use the TaskPolicy
        return $this->user()->hasPermission('task.update', $organisationId);
    }

    /**
     * Get custom error messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'The due date must be after or equal to the start date.',
            'parent_task_id.exists' => 'The selected parent task does not exist.',
            'responsible_id.exists' => 'The selected responsible user does not exist or is not active.',
            'reporter_id.exists' => 'The selected reporter does not exist or is not active.',
            'board_id.exists' => 'The selected board does not exist.',
            'board_column_id.exists' => 'The selected board column does not exist.',
        ];
    }
}
