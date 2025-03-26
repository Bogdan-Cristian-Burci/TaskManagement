<?php

namespace App\Services;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\BoardType;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BoardService
{
    /**
     * Create a board for a project using a specific board type.
     *
     * @param Project $project
     * @param int $boardTypeId
     * @param array $attributes Additional board attributes
     * @return Board
     */
    public function createBoard(Project $project, int $boardTypeId, array $attributes = []): Board
    {
        $boardType = BoardType::findOrFail($boardTypeId);

        $boardData = array_merge([
            'name' => $project->name . ' Board',
            'project_id' => $project->id,
            'description' => 'Default board for ' . $project->name,
        ], $attributes);

        return $boardType->createBoard($boardData);
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
     * Archive a board.
     *
     * @param Board $board
     * @return Board
     */
    public function archiveBoard(Board $board): Board
    {
        $board->archive();
        return $board->fresh();
    }

    /**
     * Unarchive a board.
     *
     * @param Board $board
     * @return Board
     */
    public function unarchiveBoard(Board $board): Board
    {
        $board->unarchive();
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
        return [
            'total_tasks' => $board->tasks->count(),
            'completed_tasks' => $board->completed_tasks_count,
            'completion_percentage' => $board->completion_percentage,
            'tasks_by_column' => $board->columns->mapWithKeys(function ($column) {
                return [$column->name => $column->tasks->count()];
            }),
            'tasks_by_status' => $board->tasks->groupBy('status.name')
                ->map(function ($tasks) {
                    return $tasks->count();
                }),
            'tasks_by_assignee' => $board->tasks->groupBy('assignee.name')
                ->map(function ($tasks) {
                    return $tasks->count();
                }),
        ];
    }
}
