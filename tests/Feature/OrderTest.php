<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $owner;
    protected $customer;
    protected $products = [];

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

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->products = [
            Product::factory()->create([
                'tenant_id' => $this->tenant->id,
                'stock_quantity' => 100,
                'price' => 50.00,
            ]),
            Product::factory()->create([
                'tenant_id' => $this->tenant->id,
                'stock_quantity' => 50,
                'price' => 25.00,
            ]),
        ];
    }

    /** @test */
    public function can_create_order_with_multiple_products()
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/orders', [
                'customer_id' => $this->customer->id,
                'items' => [
                    [
                        'product_id' => $this->products[0]->id,
                        'quantity' => 2,
                    ],
                    [
                        'product_id' => $this->products[1]->id,
                        'quantity' => 3,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'order' => [
                    'id', 'order_number', 'total_amount', 'status',
                    'items' => [
                        '*' => ['product', 'quantity', 'unit_price', 'total_price']
                    ]
                ]
            ]);

        $response->assertJsonPath('order.total_amount', 175);
        $this->products[0]->refresh();
        $this->products[1]->refresh();
        $this->assertEquals(98, $this->products[0]->stock_quantity);
        $this->assertEquals(47, $this->products[1]->stock_quantity);
    }

    /** @test */
    public function cannot_create_order_with_insufficient_stock()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10,
                    ]
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    /** @test */
    public function can_list_orders()
    {
        Order::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'order_number', 'total_amount', 'status']
                ],
                'links',
                'meta'
            ])
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function can_filter_orders_by_status()
    {
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'paid',
        ]);
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/orders?status=paid');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');
    }

    /** @test */
    public function can_cancel_order_and_restore_stock()
    {
        $orderResponse = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->products[0]->id,
                        'quantity' => 10,
                    ]
                ]
            ]);

        $orderId = $orderResponse->json('order.id');

        $this->products[0]->refresh();
        $this->assertEquals(90, $this->products[0]->stock_quantity);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/orders/{$orderId}/cancel");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Order cancelled successfully']);

        $this->products[0]->refresh();
        $this->assertEquals(100, $this->products[0]->stock_quantity);
    }

    /** @test */
    public function can_mark_order_as_paid()
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/orders/{$order->id}/mark-as-paid");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Order marked as paid']);

        $order->refresh();
        $this->assertEquals('paid', $order->status);
    }

    /** @test */
    public function cannot_cancel_paid_order()
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200);
    }

    /** @test */
    public function cannot_delete_non_pending_order()
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Only pending orders can be deleted']);
    }
}