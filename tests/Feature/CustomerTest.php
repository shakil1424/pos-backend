<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $owner;
    protected $staff;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
        ]);
        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function owner_can_list_customers()
    {
        Customer::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ])
            ->assertJsonCount(6, 'data'); // 5 new + 1 from setUp
    }

    /** @test */
    public function staff_can_list_customers()
    {
        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/customers');

        $response->assertStatus(200);
    }

    /** @test */
    public function can_search_customers_by_name()
    {
        $john = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ricky',
        ]);

        $jane = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jane Smith',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/customers?search=Ricky');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ricky');
    }

    /** @test */
    public function can_search_customers_by_email()
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'john@example.com',
        ]);

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'jane@example.com',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/customers?search=jane@example.com');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'jane@example.com');
    }

    /** @test */
    public function can_search_customers_by_phone()
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '1234567890',
        ]);

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '0987654321',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/customers?search=1234567890');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.phone', '1234567890');
    }

    /** @test */
    public function owner_can_create_customer()
    {
        $customerData = [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'phone' => '1234567890',
            'address' => '123 Street, City',
        ];

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'customer' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'address',
                ]
            ])
            ->assertJson([
                'message' => 'Customer created successfully',
                'customer' => [
                    'name' => 'New Customer',
                    'email' => 'new@example.com',
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'name' => 'New Customer',
            'email' => 'new@example.com',
        ]);
    }

    /** @test */
    public function staff_can_create_customer()
    {
        $customerData = [
            'name' => 'Staff Created Customer',
            'email' => 'staff@example.com',
            'phone' => '5555555555',
        ];

        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(201);
    }

    /** @test */
    public function customer_creation_requires_valid_data()
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/customers', [
                'name' => '', // Required
                'email' => 'invalid-email', // Invalid email
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    /** @test */
    public function owner_can_view_customer_details()
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/customers/{$this->customer->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'customer' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'address',
                ]
            ])
            ->assertJson([
                'customer' => [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                ]
            ]);
    }

    /** @test */
    public function staff_can_view_customer_details()
    {
        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/customers/{$this->customer->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function cannot_view_customer_from_different_tenant()
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/customers/{$otherCustomer->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function owner_can_update_customer()
    {
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '9876543210',
            'address' => 'Updated Address',
        ];

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->putJson("/api/customers/{$this->customer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer updated successfully',
                'customer' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /** @test */
    public function staff_can_update_customer()
    {
        $updateData = [
            'name' => 'Staff Updated Name',
            'email' => 'staffupdated@example.com',
        ];

        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->putJson("/api/customers/{$this->customer->id}", $updateData);

        $response->assertStatus(200);
    }


    /** @test */
    public function owner_can_delete_customer_without_orders()
    {
        $customerWithoutOrders = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/api/customers/{$customerWithoutOrders->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer deleted successfully'
            ]);

        $this->assertSoftDeleted('customers', [
            'id' => $customerWithoutOrders->id,
        ]);
    }

    /** @test */
    public function cannot_delete_customer_with_orders()
    {
        $customerWithOrders = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerWithOrders->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/api/customers/{$customerWithOrders->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete customer with existing orders. Consider archiving instead.'
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerWithOrders->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function staff_cannot_delete_customer()
    {
        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/api/customers/{$this->customer->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function owner_can_restore_deleted_customer()
    {
        $deletedCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deleted_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/customers/{$deletedCustomer->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer restored successfully'
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $deletedCustomer->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function staff_cannot_restore_customer()
    {
        $deletedCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deleted_at' => now(),
        ]);

        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/customers/{$deletedCustomer->id}/restore");

        $response->assertStatus(403);
    }

    /** @test */
    public function cannot_restore_non_existent_customer()
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/customers/9999/restore");

        $response->assertStatus(404);
    }

    /** @test */
    public function can_paginate_customers()
    {
        Customer::factory()->count(25)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/customers?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ]
            ])
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 26); // 25 new + 1 from setUp
    }



    /** @test */
    public function customers_list_requires_authentication()
    {
        $response = $this->getJson('/api/customers');

        $response->assertStatus(401);
    }

    /** @test */
    public function customer_creation_requires_tenant_header()
    {
        $customerData = [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ];

        $response = $this->actingAs($this->owner)
            ->postJson('/api/customers', $customerData);

        // This might return 500 or validation error depending on your setup
        $response->assertStatus(400); // Or assert validation error
    }

}