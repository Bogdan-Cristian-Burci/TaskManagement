<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BoardApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with appropriate permissions
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['create board', 'update board', 'delete board']);

        // Create a project and add the user to it
        $this->project = Project::factory()->create();
        $this->project->users()->attach($this->user->id, ['role' => 'manager']);

        // Authenticate the user
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_boards_for_a_project()
    {
        // Create some boards for the project
        $boards = Board::factory()
            ->count(3)
            ->forProject($this->project)
            ->create();

        // Create a board for another project (should not be returned)
        $otherProject = Project::factory()->create();
        Board::factory()->forProject($otherProject)->create();

        // Make the request
        $response = $this->getJson(route('projects.boards.index', $this->project->id));

        // Assert response
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment(['name' => $boards[0]->name]);
    }

    /** @test */
    public function it_can_create_a_board()
    {
        $boardType = \App\Models\BoardType::factory()->create();

        $boardData = [
            'name' => 'Test Board',
            'description' => 'This is a test board',
            'type' => 'scrum',
            'project_id' => $this->project->id,
            'board_type_id' => $boardType->id
        ];

        $response = $this->postJson(route('boards.store'), $boardData);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'Test Board']);

        $this->assertDatabaseHas('boards', [
            'name' => 'Test Board',
            'project_id' => $this->project->id
        ]);
    }

    /** @test */
    public function it_validates_board_creation_data()
    {
        $response = $this->postJson(route('boards.store'), [
            // Missing required fields
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'project_id', 'board_type_id']);
    }

    /** @test */
    public function it_can_show_a_board()
    {
        $board = Board::factory()
            ->forProject($this->project)
            ->withColumns()
            ->create();

        $response = $this->getJson(route('boards.show', $board));

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $board->name]);

        // Test with includes
        $response = $this->getJson(route('boards.show', [
            'board' => $board->id,
            'include' => 'project,columns',
            'with_counts' => true
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('data.project.id', $this->project->id);
        $response->assertJsonCount(3, 'data.columns');
        $response->assertJsonPath('data.columns_count', 3);
    }

    /** @test */
    public function it_can_update_a_board()
    {
        $board = Board::factory()
            ->forProject($this->project)
            ->create();

        $updateData = [
            'name' => 'Updated Board Name',
            'description' => 'Updated description',
            'project_id' => $this->project->id,
            'board_type_id' => $board->board_type_id
        ];

        $response = $this->putJson(route('boards.update', $board), $updateData);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Board Name']);

        $this->assertDatabaseHas('boards', [
            'id' => $board->id,
            'name' => 'Updated Board Name'
        ]);
    }

    /** @test */
    public function it_can_delete_a_board()
    {
        $board = Board::factory()
            ->forProject($this->project)
            ->create();

        $response = $this->deleteJson(route('boards.destroy', $board));

        $response->assertStatus(204);
        $this->assertSoftDeleted('boards', ['id' => $board->id]);
    }

    /** @test */
    public function it_can_archive_and_unarchive_a_board()
    {
        $board = Board::factory()
            ->forProject($this->project)
            ->create(['is_archived' => false]);

        // Archive the board
        $response = $this->postJson(route('boards.archive', $board));

        $response->assertStatus(200);
        $this->assertDatabaseHas('boards', [
            'id' => $board->id,
            'is_archived' => true
        ]);

        // Unarchive the board
        $response = $this->postJson(route('boards.unarchive', $board));

        $response->assertStatus(200);
        $this->assertDatabaseHas('boards', [
            'id' => $board->id,
            'is_archived' => false
        ]);
    }

    /** @test */
    public function it_can_duplicate_a_board()
    {
        $board = Board::factory()
            ->forProject($this->project)
            ->withColumns()
            ->create();

        $response = $this->postJson(route('boards.duplicate', $board), [
            'name' => 'Duplicated Board'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Duplicated Board');

        $this->assertDatabaseHas('boards', [
            'name' => 'Duplicated Board',
            'project_id' => $this->project->id
        ]);

        // The duplicated board should have the same number of columns
        $newBoardId = $response->json('data.id');
        $columnsCount = \App\Models\BoardColumn::where('board_id', $newBoardId)->count();
        $this->assertEquals(3, $columnsCount);
    }

    /** @test */
    public function it_can_restore_a_deleted_board()
    {
        $board = Board::factory()
            ->forProject($this->project)
            ->create();

        $board->delete();
        $this->assertSoftDeleted('boards', ['id' => $board->id]);

        $response = $this->postJson(route('boards.restore', $board->id));

        $response->assertStatus(200);
        $this->assertDatabaseHas('boards', [
            'id' => $board->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function it_can_get_board_statistics()
    {
        $board = Board::factory()
            ->forProject($this->project)
            ->withColumns()
            ->create();

        // Create some tasks for the board with different statuses
        $board->tasks()->createMany([
            ['title' => 'Task 1', 'status' => 'to_do', 'assignee_id' => $this->user->id],
            ['title' => 'Task 2', 'status' => 'in_progress', 'assignee_id' => $this->user->id],
            ['title' => 'Task 3', 'status' => 'completed', 'assignee_id' => null],
            ['title' => 'Task 4', 'status' => 'completed', 'assignee_id' => $this->user->id]
        ]);

        $response = $this->getJson(route('boards.statistics', $board));

        $response->assertStatus(200);
        $response->assertJsonPath('tasks_by_status.to_do', 1);
        $response->assertJsonPath('tasks_by_status.in_progress', 1);
        $response->assertJsonPath('tasks_by_status.completed', 2);
        $response->assertJsonPath('total_tasks', 4);
        $response->assertJsonPath('completed_tasks', 2);
        $response->assertJsonPath('completion_percentage', 50);
        $response->assertJsonPath('tasks_by_assignee.' . $this->user->id, 3);
    }
}
