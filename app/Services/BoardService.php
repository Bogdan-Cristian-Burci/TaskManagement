<?php

namespace App\Services;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\BoardTemplate;
use App\Models\BoardType;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BoardService
{
    protected SprintService $sprintService;

    public function __construct(SprintService $sprintService)
    {
        $this->sprintService = $sprintService;
    }

    /**
     * Create a board for a project using a specific board type.
     *
     * @param Project $project
     * @param int $boardTypeId
     * @param array $attributes Additional board attributes
     * @return Board
     * @throws \Exception
     */
    public function createBoard(Project $project, int $boardTypeId, array $attributes = []): Board
    {
        $boardType = BoardType::findOrFail($boardTypeId);

        // Load template without global scopes that might be filtering it
        $template = BoardTemplate::withoutGlobalScopes()
            ->where('id', $boardType->template_id)
            ->first();

        \Log::info('Loading template directly: ', [
            'template_id' => $boardType->template_id,
            'template_found' => $template ? 'yes' : 'no'
        ]);

        // Manually attach the template to the board type
        $boardType->setRelation('template', $template);


        return DB::transaction(function() use ($project, $boardType, $attributes) {
            // Create board with default attributes
            $boardData = array_merge([
                'name' => $project->name . ' Board',
                'project_id' => $project->id,
                'description' => 'Default board for ' . $project->name,
            ], $attributes);

            $board = $boardType->createBoard($boardData);

            if (!$board) {
                throw new \Exception('Failed to create board using template for board type: ' . $boardType->name);
            }

            // Initialize based on board type
            $this->initializeBoardByType($board, $boardType);

            return $board;
        });
    }

    /**
     * Initialize board with type-specific configuration.
     */
    protected function initializeBoardByType(Board $board, BoardType $boardType): void
    {
        // Get template settings
        $settings = $boardType->template->settings ?? [];

        // Initialize Scrum-specific features
        if (isset($settings['sprint_support']) && $settings['sprint_support'] &&
            strtolower($boardType->name) === 'scrum') {

            // Create first sprint
            $this->createInitialSprint($board);
        }
    }

    /**
     * Create initial sprint for Scrum boards.
     */
    protected function createInitialSprint(Board $board): void
    {
        $startDate = now();
        $endDate = now()->addWeeks(2); // Default 2-week sprint

        $this->sprintService->createSprint($board, [
            'name' => 'Sprint 1',
            'status' => 'planning',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'goal' => 'Initial sprint'
        ]);
    }

    /**
     * Get all boards with optional filtering.
     *
     * @param array $filters Array of filter conditions
     * @param array $with Related models to load
     * @return Collection
     */
    public function getBoards(array $filters = [], array $with = []): Collection
    {
        $query = Board::query();

        // Apply filters
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['board_type_id'])) {
            $query->where('board_type_id', $filters['board_type_id']);
        }

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get a specific board by ID.
     *
     * @param int $boardId
     * @param array $with Related models to load
     * @return Board|null
     */
    public function getBoard(int $boardId, array $with = []): ?Board
    {
        return Board::with($with)->find($boardId);
    }

    /**
     * Update a board's details.
     *
     * @param Board $board
     * @param array $attributes
     * @return Board
     */
    public function updateBoard(Board $board, array $attributes): Board
    {
        $board->update($attributes);
        return $board->fresh();
    }

    /**
     * Duplicate a board.
     *
     * @param Board $board
     * @param string|null $newName
     * @return Board
     */
    public function duplicateBoard(Board $board, ?string $newName = null): Board
    {
        return $board->duplicate($newName);
    }

    /**
     * Create a column on a board.
     *
     * @param Board $board
     * @param array $attributes
     * @return BoardColumn
     */
    public function createColumn(Board $board, array $attributes): BoardColumn
    {
        return $board->columns()->create($attributes);
    }

    /**
     * Update a column.
     *
     * @param BoardColumn $column
     * @param array $attributes
     * @return BoardColumn
     */
    public function updateColumn(BoardColumn $column, array $attributes): BoardColumn
    {
        $column->update($attributes);
        return $column->fresh();
    }

    /**
     * Delete a column.
     *
     * @param BoardColumn $column
     * @return bool
     */
    public function deleteColumn(BoardColumn $column): bool
    {
        return $column->delete();
    }

    /**
     * Reorder columns on a board.
     *
     * @param Board $board
     * @param array $columnPositions Array of column IDs and their new positions
     * @return Collection
     * @throws \Throwable
     */
    public function reorderColumns(Board $board, array $columnPositions): Collection
    {
        DB::transaction(function() use ($columnPositions) {
            foreach ($columnPositions as $column) {
                BoardColumn::find($column['id'])->update(['position' => $column['position']]);
            }
        });

        return $board->columns()->orderBy('position')->get();
    }

    /**
     * Move a task to a new column.
     *
     * @param Task $task
     * @param BoardColumn $targetColumn
     * @param bool $force Whether to bypass workflow rules
     * @return bool
     */
    public function moveTask(Task $task, BoardColumn $targetColumn, bool $force = false): bool
    {
        // Check if columns belong to the same board
        if ($task->boardColumn && $task->boardColumn->board_id !== $targetColumn->board_id) {
            return false;
        }

        return $task->moveToColumn($targetColumn, $force);
    }

    /**
     * Get board statistics.
     *
     * @param Board $board
     * @return array
     */
    public function getBoardStatistics(Board $board): array
    {
        // Use query builder for more efficient counting
        $totalTasks = $board->tasks()->count();
        $completedTasks = $board->completedTasks()->count();
        $columnsCount = $board->columns()->count();
        $overdueTasks = $board->tasks()->overdue()->count();
        
        // Calculate completion percentage
        $completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
        
        // Load minimal relationships needed for the task grouping
        $board->load([
            'columns:id,name,board_id',
            'activeSprint',
        ]);
        
        // Get counts by column using query builder for efficiency
        $tasksByColumn = $board->columns->mapWithKeys(function ($column) {
            return [$column->name => $column->tasks()->count()];
        });
        
        // Get counts by status using a single query
        $tasksByStatus = DB::table('tasks')
            ->join('statuses', 'tasks.status_id', '=', 'statuses.id')
            ->where('tasks.board_id', $board->id)
            ->select('statuses.name', DB::raw('count(*) as count'))
            ->groupBy('statuses.name')
            ->pluck('count', 'name');
            
        // Get counts by assignee using a single query
        $tasksByAssignee = DB::table('tasks')
            ->leftJoin('users', 'tasks.assignee_id', '=', 'users.id')
            ->where('tasks.board_id', $board->id)
            ->select('users.name', DB::raw('count(*) as count'))
            ->groupBy('users.name')
            ->pluck('count', 'name');
        
        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'completion_percentage' => $completionPercentage,
            'tasks_by_column' => $tasksByColumn,
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_assignee' => $tasksByAssignee,
            'active_sprint' => $board->activeSprint,
            'columns_count' => $columnsCount,
            'overdue_tasks' => $overdueTasks,
        ];
    }

    /**
     * Delete a board and related entities.
     *
     * @param Board $board
     * @param bool $cascadeDelete Whether to delete related tasks
     * @return bool
     */
    public function deleteBoard(Board $board, bool $cascadeDelete = false): bool
    {
        return DB::transaction(function() use ($board, $cascadeDelete) {
            // Bulk detach sprint tasks and delete sprints with a more efficient approach
            $sprintIds = $board->sprints()->pluck('id');
            if ($sprintIds->isNotEmpty()) {
                // Detach all tasks from all sprints in this board at once
                DB::table('sprint_task')
                    ->whereIn('sprint_id', $sprintIds)
                    ->delete();
                    
                // Delete all sprints at once
                $board->sprints()->delete();
            }

            if ($cascadeDelete) {
                // Bulk delete all tasks in this board
                $board->tasks()->delete();
            } else {
                // Bulk update all tasks to detach them from the board and columns
                $board->tasks()->update([
                    'board_column_id' => null,
                    'board_id' => null
                ]);
            }

            // Delete all columns at once
            $board->columns()->delete();

            // Delete the board
            return $board->delete();
        });
    }

    /**
     * Get tasks on a board with optional filtering.
     *
     * @param Board $board
     * @param array $filters
     * @param array $with Related models to load
     * @return Collection
     */
    public function getBoardTasks(Board $board, array $filters = [], array $with = []): Collection
    {
        $query = $board->tasks();

        // Apply filters
        if (isset($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        if (isset($filters['column_id'])) {
            $query->where('board_column_id', $filters['column_id']);
        }

        if (isset($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }
}
