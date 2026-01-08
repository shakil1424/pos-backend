<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant1;
    protected $tenant2;
    protected $user1;
    protected $user2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenants
        $this->tenant1 = Tenant::factory()->create();
        $this->tenant2 = Tenant::factory()->create();

        // Create users
        $this->user1 = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'role' => 'owner',
        ]);

        $this->user2 = User::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_cannot_access_other_tenant_products()
    {
        // Create product in tenant2
        $product = Product::factory()->create(['tenant_id' => $this->tenant2->id]);

        // User1 tries to access tenant2's product
        $response = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->getJson("/api/products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_only_see_own_tenant_products()
    {
        // Create products in both tenants
        Product::factory()->count(3)->create(['tenant_id' => $this->tenant1->id]);
        Product::factory()->count(2)->create(['tenant_id' => $this->tenant2->id]);

        // User1 should only see 3 products
        $response = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->getJson('/api/products');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_staff_cannot_create_product()
    {
        $staff = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'sku' => 'TEST-001',
                'price' => 99.99,
                'stock_quantity' => 100,
                'low_stock_threshold' => 10,
            ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_create_product()
    {
        $response = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'sku' => 'TEST-001',
                'price' => 99.99,
                'stock_quantity' => 100,
                'low_stock_threshold' => 10,
            ]);

        $response->assertStatus(201);
    }

    public function test_sku_must_be_unique_per_tenant()
    {
        // Create product with SKU TEST-001 in tenant1
        Product::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'sku' => 'TEST-001',
        ]);

        // Try to create another product with same SKU in same tenant
        $response = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->postJson('/api/products', [
                'name' => 'Another Product',
                'sku' => 'TEST-001', // Same SKU
                'price' => 49.99,
                'stock_quantity' => 50,
                'low_stock_threshold' => 5,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sku']);
    }

    public function test_same_sku_can_exist_in_different_tenants()
    {
        // Create product with SKU TEST-001 in tenant1
        Product::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'sku' => 'TEST-001',
        ]);

        // Create product with same SKU in tenant2 (should work)
        $response = $this->actingAs($this->user2)
            ->withHeaders(['X-Tenant-ID' => $this->tenant2->id])
            ->postJson('/api/products', [
                'name' => 'Different Product',
                'sku' => 'TEST-001', // Same SKU, different tenant
                'price' => 49.99,
                'stock_quantity' => 50,
                'low_stock_threshold' => 5,
            ]);

        $response->assertStatus(201);
    }

    public function test_order_creation_deducts_stock()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'stock_quantity' => 100,
        ]);

        $initialStock = $product->stock_quantity;

        $response = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 5,
                    ]
                ]
            ]);

        $response->assertStatus(201);

        $product->refresh();
        $this->assertEquals(95, $product->stock_quantity);
    }

    public function test_order_cancellation_restores_stock()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'stock_quantity' => 100,
        ]);

        // Create order
        $orderResponse = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10,
                    ]
                ]
            ]);

        $orderId = $orderResponse->json('order.id');
        $product->refresh();
        $this->assertEquals(90, $product->stock_quantity);

        // Cancel order
        $response = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->postJson("/api/orders/{$orderId}/cancel");

        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals(100, $product->stock_quantity);
    }

    public function test_cannot_create_order_with_insufficient_stock()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10, // More than available
                    ]
                ]
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_staff_can_access_reports()
    {
        $staff = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->getJson('/api/reports/low-stock');

        $response->assertStatus(403); // Staff cannot access reports
    }

    public function test_owner_can_access_reports()
    {
        $response = $this->actingAs($this->user1)
            ->withHeaders(['X-Tenant-ID' => $this->tenant1->id])
            ->getJson('/api/reports/low-stock');

        $response->assertStatus(200);
    }
}