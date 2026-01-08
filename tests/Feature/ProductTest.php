<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $owner;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password123'),
        ]);
        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
            'email' => 'staff@test.com',
            'password' => bcrypt('password123'),
        ]);
    }

    /** @test */
    public function can_list_products()
    {
        Product::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'sku', 'price', 'stock_quantity']
                ],
                'links',
                'meta'
            ])
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function owner_can_create_product()
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/products', [
                'name' => 'New Product',
                'sku' => 'NEW-001',
                'price' => 99.99,
                'stock_quantity' => 100,
                'low_stock_threshold' => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'product' => ['id', 'name', 'sku', 'price']
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
            'sku' => 'NEW-001',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function staff_cannot_create_product()
    {
        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/products', [
                'name' => 'New Product',
                'sku' => 'NEW-001',
                'price' => 99.99,
                'stock_quantity' => 100,
                'low_stock_threshold' => 10,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function can_show_product()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'product' => ['id', 'name', 'sku', 'price']
            ]);
    }

    /** @test */
    public function owner_can_update_product()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Updated Product',
                'price' => 149.99,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Product updated successfully',
                'product' => ['name' => 'Updated Product', 'price' => 149.99]
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
            'price' => 149.99,
        ]);
    }

    /** @test */
    public function staff_cannot_update_product()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Updated Product',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function owner_can_delete_product()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Product deleted successfully']);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /** @test */
    public function staff_cannot_delete_product()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function cannot_delete_product_with_orders()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 100,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
        ]);

        $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete product with existing orders. Consider deactivating instead.'
            ]);
    }

    /** @test */
    public function can_filter_low_stock_products()
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 5,
            'low_stock_threshold' => 10,
        ]);
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/products?low_stock=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}