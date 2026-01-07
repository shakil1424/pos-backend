<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'tenant_name' => 'Test Business',
            'domain' => 'test-business.local',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'tenant' => ['id', 'name', 'domain'],
                'token',
            ]);
    }

    public function test_user_can_login()
    {
        // First register
        $this->postJson('/api/register', [
            'tenant_name' => 'Test Business',
            'domain' => 'test-business.local',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Then login
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_user_can_logout()
    {
        // Register and get token
        $registerResponse = $this->postJson('/api/register', [
            'tenant_name' => 'Test Business',
            'domain' => 'test-business.local',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('token');
        $tenantId = $registerResponse->json('tenant.id');

        // Logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId,
        ])->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_user_can_get_current_user()
    {
        // Register
        $registerResponse = $this->postJson('/api/register', [
            'tenant_name' => 'Test Business',
            'domain' => 'test-business.local',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('token');
        $tenantId = $registerResponse->json('tenant.id');

        // Get current user
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId,
        ])->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role']]);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}