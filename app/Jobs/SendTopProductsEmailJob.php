<?php

namespace App\Jobs;

use App\Mail\TopProductsReport;
use App\Models\OrderItem;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendTopProductsEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $startDate,
        public string $endDate,
        public string $email
    ) {}

    public function handle()
    {
        $topProducts = $this->getTopProducts();
        $tenant = Tenant::find($this->tenantId);

        // Send email (you'll need to create this mail class)
        Mail::to($this->email)->send(new TopProductsReport(
            $topProducts,
            $this->startDate,
            $this->endDate,
            $tenant->name
        ));
    }

    private function getTopProducts()
    {
        return OrderItem::select(
            'products.id',
            'products.name',
            'products.sku',
            DB::raw('SUM(order_items.quantity) as total_quantity'),
            DB::raw('SUM(order_items.total_price) as total_revenue'),
            DB::raw('AVG(order_items.unit_price) as average_price')
        )
            ->join('orders', function ($join) {
                $join->on('order_items.order_id', '=', 'orders.id')
                    ->where('orders.tenant_id', $this->tenantId);
            })
            ->join('products', function ($join) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->where('products.tenant_id', $this->tenantId);
            })
            ->whereBetween('orders.created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->where('orders.status', 'paid')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();
    }
}