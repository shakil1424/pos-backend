<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\DailySalesSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendTopProductsEmailJob;

class ReportTest extends TestCase
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
    public function owner_can_access_daily_sales_report_with_pre_generated_summary()
    {
        // Create a pre-generated daily summary
        $yesterday = now()->subDay()->format('Y-m-d');
        DailySalesSummary::factory()->create([
            'tenant_id' => $this->tenant->id,
            'date' => $yesterday,
            'total_orders' => 5,
            'total_sales' => 1000.50,
            'average_order_value' => 200.10,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/reports/daily-sales?date={$yesterday}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'summary' => [
                    'date',
                    'total_orders',
                    'total_sales',
                    'average_order_value',
                    'source'
                ]
            ])
            ->assertJson([
                'date' => $yesterday,
                'summary' => [
                    'source' => 'pre-generated'
                ]
            ]);
    }

    /** @test */
    public function owner_can_access_daily_sales_report_with_on_demand_generation()
    {
        $yesterday = now()->subDay()->format('Y-m-d');

        // Create paid orders for yesterday
        Order::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'paid',
            'total_amount' => 100.00,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/reports/daily-sales?date={$yesterday}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'summary'
            ])
            ->assertJson([
                'date' => $yesterday,
                'summary' => [
                    'source' => 'on-demand'
                ]
            ]);
    }

    /** @test */
    public function daily_sales_report_uses_yesterday_as_default_date()
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/reports/daily-sales');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'summary'
            ]);
    }

    /** @test */
    public function staff_cannot_access_daily_sales_report()
    {
        $response = $this->actingAs($this->staff)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/reports/daily-sales');

        $response->assertStatus(403);
    }

    /** @test */
    public function owner_can_access_top_products_report_with_immediate_generation()
    {
        // Set config to allow immediate generation for small date ranges
        config(['reports.immediate_threshold_days' => 30]);

        // Create orders with products
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'paid',
            'created_at' => now()->subDays(5),
        ]);

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
            'total_price' => 500,
        ]);


        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson(
                '/api/reports/top-products?start_date=' . now()->subDays(7)->format('Y-m-d') . '&end_date=' . now(
                )->format('Y-m-d')
            );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'start_date',
                'end_date',
                'generated',
                'days_processed',
                'top_products'
            ])
            ->assertJson([
                'generated' => 'immediate'
            ]);
    }

    /** @test */
   /* public function top_products_report_queues_email_for_large_date_ranges()
    {
        Queue::fake();

        // Set config to small threshold to trigger email queueing
        config(['reports.immediate_threshold_days' => 7]);

        $startDate = now()->subDays(60)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/reports/top-products?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'start_date',
                'end_date',
                'days_processed',
                'email',
                'estimated_time',
                'note'
            ])
            ->assertJson([
                'generated' => null, // Not in immediate response
                'message' => 'Report generation queued for email delivery'
            ]);

        // Assert job was queued
        Queue::assertPushed(SendTopProductsEmailJob::class, function ($job) use ($startDate, $endDate) {
            return $job->tenantId === $this->tenant->id &&
                $job->startDate === $startDate &&
                $job->endDate === $endDate;
        });
    }*/
    public function top_products_report_queues_email_for_large_date_ranges()
    {
        Queue::fake();

        // Set config to small threshold to trigger email queueing
        config(['reports.immediate_threshold_days' => 7]);

        $startDate = now()->subDays(60)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/reports/top-products?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'start_date',
                'end_date',
                'days_processed',
                'email',
                'estimated_time',
                'note'
            ])
            ->assertJson([
                'message' => 'Report generation queued for email delivery',
                // Remove 'generated' => null since it's not in the response
            ]);

        // Assert job was queued
        Queue::assertPushed(SendTopProductsEmailJob::class, function ($job) use ($startDate, $endDate) {
            return $job->tenantId === $this->tenant->id &&
                $job->startDate === $startDate &&
                $job->endDate === $endDate;
        });
    }
    /** @test */
    public function top_products_report_uses_default_date_range_when_not_provided()
    {
        config(['reports.immediate_threshold_days' => 30]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/reports/top-products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'start_date',
                'end_date',
                'generated',
                'days_processed'
            ]);
    }

    /** @test */
    public function owner_can_access_low_stock_report()
    {
        // Create low stock products
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 5,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 20,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/reports/low-stock');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'generated',
                'timestamp',
                'low_stock_products',
                'count'
            ])
            ->assertJson([
                'generated' => 'immediate',
                'count' => 1 // Only one low stock product
            ]);
    }

    /** @test */
    public function low_stock_report_only_includes_active_products()
    {
        // Create inactive low stock product (should not appear in report)
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 2,
            'low_stock_threshold' => 10,
            'is_active' => false,
        ]);

        // Create active low stock product (should appear in report)
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 5,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/reports/low-stock');

        $response->assertStatus(200)
            ->assertJson(['count' => 1]); // Only active product should be counted
    }

    /** @test */
    public function reports_require_tenant_header()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/reports/daily-sales');

        // This will likely return 500 or validation error since tenant_id is required
        // Adjust based on your actual validation
        $response->assertStatus(400); // Or assert validation error
    }

    /** @test */
    public function daily_sales_report_validates_date_format()
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/reports/daily-sales?date=invalid-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    /** @test */
    public function top_products_report_validates_date_range()
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson(
                '/api/reports/top-products?start_date=' . now()->format('Y-m-d') . '&end_date=' . now()->subDays(
                    1
                )->format('Y-m-d')
            );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    /** @test */
    public function test_daily_sales_report_with_multiple_tenants()
    {
        // Create another tenant
        $tenant2 = Tenant::factory()->create();
        $owner2 = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'role' => 'owner',
        ]);

        // Create data for both tenants
        DailySalesSummary::factory()->create([
            'tenant_id' => $this->tenant->id,
            'date' => '2024-01-01',
            'total_orders' => 10,
        ]);

        DailySalesSummary::factory()->create([
            'tenant_id' => $tenant2->id,
            'date' => '2024-01-01',
            'total_orders' => 20,
        ]);

        // Test first tenant
        $response1 = $this->actingAs($this->owner)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/reports/daily-sales?date=2024-01-01');

        $response1->assertStatus(200)
            ->assertJsonPath('summary.total_orders', 10);

        // Test second tenant
        $response2 = $this->actingAs($owner2)
            ->withHeaders(['X-Tenant-ID' => $tenant2->id])
            ->getJson('/api/reports/daily-sales?date=2024-01-01');

        $response2->assertStatus(200)
            ->assertJsonPath('summary.total_orders', 20);
    }
}