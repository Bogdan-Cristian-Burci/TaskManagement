<?php

namespace Tests\Feature;

use Tests\TestCase;

class SprintRoutesTest extends TestCase
{
    /** @test */
    public function sprint_api_routes_should_require_authentication()
    {
        // Test unauthenticated access to board sprints
        $response = $this->getJson('/api/boards/1/sprints');
        $this->assertTrue($response->status() === 401 || $response->status() === 403, 
            'Board sprints route should require authentication');
        
        // Test unauthenticated access to sprints
        $response = $this->getJson('/api/sprints/1');
        $this->assertTrue($response->status() === 401 || $response->status() === 403, 
            'Sprint route should require authentication');
        
        // Test unauthenticated access to sprint tasks
        $response = $this->getJson('/api/sprints/1/tasks');
        $this->assertTrue($response->status() === 401 || $response->status() === 403, 
            'Sprint tasks route should require authentication');
        
        // Test unauthenticated access to sprint update
        $response = $this->patchJson('/api/sprints/1', ['name' => 'Updated Sprint']);
        $this->assertTrue($response->status() === 401 || $response->status() === 403, 
            'Sprint update route should require authentication');
        
        // Test unauthenticated access to sprint start
        $response = $this->postJson('/api/sprints/1/start');
        $this->assertTrue($response->status() === 401 || $response->status() === 403, 
            'Sprint start route should require authentication');
        
        // Test unauthenticated access to sprint complete
        $response = $this->postJson('/api/sprints/1/complete');
        $this->assertTrue($response->status() === 401 || $response->status() === 403, 
            'Sprint complete route should require authentication');
        
        // Test unauthenticated access to sprint delete
        $response = $this->deleteJson('/api/sprints/1');
        $this->assertTrue($response->status() === 401 || $response->status() === 403, 
            'Sprint delete route should require authentication');
    }
}