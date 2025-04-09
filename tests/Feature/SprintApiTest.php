<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SprintApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Board $board;
    private Project $project;
    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization first
        $this->organisation = Organisation::factory()->create();
        
        // Create user with appropriate permissions
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['create sprint', 'update sprint', 'delete sprint']);
        
        // Link user to organization
        $this->organisation->users()->attach($this->user->id, ['role' => 'admin']);
        
        // Create project in the organization
        $this->project = Project::factory()->create([
            'organisation_id' => $this->organisation->id
        ]);
        
        // Create a board for the project
        $this->board = Board::factory()
            ->forProject($this->project)
            ->create();
            
        // Authenticate the user
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_get_sprints_for_a_board()
    {
        // Create sprints for the board
        $sprints = Sprint::factory()
            ->count(3)
            ->create([
                'board_id' => $this->board->id,
                'organisation_id' => $this->organisation->id
            ]);
            
        // Create a sprint for another board (should not be returned)
        $otherBoard = Board::factory()->forProject($this->project)->create();
        Sprint::factory()->create([
            'board_id' => $otherBoard->id,
            'organisation_id' => $this->organisation->id
        ]);
        
        // Make the request
        $response = $this->getJson(route('boards.sprints.index', $this->board->id));
        
        // Assert response
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment(['name' => $sprints[0]->name]);
    }
    
    /** @test */
    public function it_can_create_a_sprint_for_a_board()
    {
        $sprintData = [
            'name' => 'Sprint 1',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(14)->format('Y-m-d'),
            'status' => 'planning',
            'board_id' => $this->board->id
        ];
        
        $response = $this->postJson(route('boards.sprints.store', $this->board->id), $sprintData);
        
        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'Sprint 1']);
        
        $this->assertDatabaseHas('sprints', [
            'name' => 'Sprint 1',
            'board_id' => $this->board->id,
            'organisation_id' => $this->organisation->id
        ]);
    }
    
    /** @test */
    public function it_can_show_a_sprint()
    {
        $sprint = Sprint::factory()->create([
            'board_id' => $this->board->id,
            'organisation_id' => $this->organisation->id
        ]);
        
        $response = $this->getJson(route('sprints.show', $sprint->id));
        
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $sprint->name]);
        
        // Test with includes
        $response = $this->getJson(route('sprints.show', [
            'sprint' => $sprint->id,
            'include' => 'board,tasks'
        ]));
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.board.id', $this->board->id);
    }
    
    /** @test */
    public function it_can_update_a_sprint()
    {
        $sprint = Sprint::factory()->create([
            'board_id' => $this->board->id,
            'organisation_id' => $this->organisation->id
        ]);
        
        $updateData = [
            'name' => 'Updated Sprint Name',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(14)->format('Y-m-d'),
            'status' => 'planning'
        ];
        
        $response = $this->patchJson(route('sprints.update', $sprint->id), $updateData);
        
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Sprint Name']);
        
        $this->assertDatabaseHas('sprints', [
            'id' => $sprint->id,
            'name' => 'Updated Sprint Name'
        ]);
    }
    
    /** @test */
    public function it_can_start_a_sprint()
    {
        $sprint = Sprint::factory()->create([
            'board_id' => $this->board->id,
            'organisation_id' => $this->organisation->id,
            'status' => 'planning'
        ]);
        
        $response = $this->postJson(route('sprints.start', $sprint->id));
        
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'active']);
        
        $this->assertDatabaseHas('sprints', [
            'id' => $sprint->id,
            'status' => 'active'
        ]);
    }
    
    /** @test */
    public function it_can_complete_a_sprint()
    {
        $sprint = Sprint::factory()->create([
            'board_id' => $this->board->id,
            'organisation_id' => $this->organisation->id,
            'status' => 'active'
        ]);
        
        // Create some tasks for this sprint
        $tasks = Task::factory()->count(3)->create([
            'board_id' => $this->board->id
        ]);
        
        // Attach tasks to sprint
        $sprint->tasks()->attach($tasks->pluck('id'));
        
        $response = $this->postJson(route('sprints.complete', $sprint->id));
        
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'completed']);
        
        $this->assertDatabaseHas('sprints', [
            'id' => $sprint->id,
            'status' => 'completed'
        ]);
    }
    
    /** @test */
    public function it_can_add_tasks_to_a_sprint()
    {
        $sprint = Sprint::factory()->create([
            'board_id' => $this->board->id,
            'organisation_id' => $this->organisation->id
        ]);
        
        // Create some tasks
        $tasks = Task::factory()->count(3)->create([
            'board_id' => $this->board->id
        ]);
        
        $response = $this->postJson(route('sprints.tasks.store', $sprint->id), [
            'task_ids' => $tasks->pluck('id')->toArray()
        ]);
        
        $response->assertStatus(200);
        
        // Check if tasks were added to the sprint
        foreach ($tasks as $task) {
            $this->assertDatabaseHas('sprint_task', [
                'sprint_id' => $sprint->id,
                'task_id' => $task->id
            ]);
        }
    }
    
    /** @test */
    public function it_can_remove_tasks_from_a_sprint()
    {
        $sprint = Sprint::factory()->create([
            'board_id' => $this->board->id,
            'organisation_id' => $this->organisation->id
        ]);
        
        // Create and attach tasks to sprint
        $tasks = Task::factory()->count(3)->create([
            'board_id' => $this->board->id
        ]);
        
        $sprint->tasks()->attach($tasks->pluck('id'));
        
        // Verify tasks are attached
        foreach ($tasks as $task) {
            $this->assertDatabaseHas('sprint_task', [
                'sprint_id' => $sprint->id,
                'task_id' => $task->id
            ]);
        }
        
        // Remove tasks
        $response = $this->deleteJson(route('sprints.tasks.destroy', $sprint->id), [
            'task_ids' => $tasks->pluck('id')->toArray()
        ]);
        
        $response->assertStatus(200);
        
        // Check if tasks were removed from the sprint
        foreach ($tasks as $task) {
            $this->assertDatabaseMissing('sprint_task', [
                'sprint_id' => $sprint->id,
                'task_id' => $task->id
            ]);
        }
    }
    
    /** @test */
    public function it_can_delete_a_sprint()
    {
        $sprint = Sprint::factory()->create([
            'board_id' => $this->board->id,
            'organisation_id' => $this->organisation->id
        ]);
        
        $response = $this->deleteJson(route('sprints.destroy', $sprint->id));
        
        $response->assertStatus(204);
        $this->assertSoftDeleted('sprints', ['id' => $sprint->id]);
    }
}