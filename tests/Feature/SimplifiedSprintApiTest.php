<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SimplifiedSprintApiTest extends TestCase
{
    /** @test */
    public function it_can_get_routes_for_sprints()
    {
        // Create a user for authentication
        $user = new User(['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com']);
        
        // Use Laravel Passport actingAs
        $this->actingAs($user, 'api');
        
        // Verify GET /boards/{board}/sprints route exists
        $response = $this->getJson('/api/boards/1/sprints');
        $this->assertNotEquals(404, $response->status());

        // Verify GET /sprints/{sprint} route exists
        $response = $this->getJson('/api/sprints/1');
        $this->assertNotEquals(404, $response->status());
        
        // Verify PATCH /sprints/{sprint} route exists
        $response = $this->patchJson('/api/sprints/1', [
            'name' => 'Updated Sprint'
        ]);
        $this->assertNotEquals(404, $response->status());
        
        // Verify POST /boards/{board}/sprints route exists
        $response = $this->postJson('/api/boards/1/sprints', [
            'name' => 'New Sprint',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(14)->format('Y-m-d'),
            'status' => 'planning'
        ]);
        $this->assertNotEquals(404, $response->status());
        
        // Verify POST /sprints/{sprint}/start route exists
        $response = $this->postJson('/api/sprints/1/start');
        $this->assertNotEquals(404, $response->status());
        
        // Verify POST /sprints/{sprint}/complete route exists
        $response = $this->postJson('/api/sprints/1/complete');
        $this->assertNotEquals(404, $response->status());
        
        // Verify GET /sprints/{sprint}/tasks route exists
        $response = $this->getJson('/api/sprints/1/tasks');
        $this->assertNotEquals(404, $response->status());
        
        // Verify POST /sprints/{sprint}/tasks route exists
        $response = $this->postJson('/api/sprints/1/tasks', [
            'task_ids' => [1, 2, 3]
        ]);
        $this->assertNotEquals(404, $response->status());
        
        // Verify DELETE /sprints/{sprint}/tasks route exists
        $response = $this->deleteJson('/api/sprints/1/tasks', [
            'task_ids' => [1, 2, 3]
        ]);
        $this->assertNotEquals(404, $response->status());
        
        // Verify DELETE /sprints/{sprint} route exists
        $response = $this->deleteJson('/api/sprints/1');
        $this->assertNotEquals(404, $response->status());
    }
}