<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\DailySalesSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateDailySalesSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $date
    ) {}

    public function handle()
    {
        $sales = Order::select(
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(total_amount) as total_sales'),
            DB::raw('AVG(total_amount) as average_order_value')
        )
            ->where('tenant_id', $this->tenantId)
            ->whereDate('created_at', $this->date)
            ->where('status', 'paid')
            ->first();

        DailySalesSummary::updateOrCreate(
            [
                'tenant_id' => $this->tenantId,
                'date' => $this->date,
            ],
            [
                'total_orders' => $sales->total_orders ?? 0,
                'total_sales' => $sales->total_sales ?? 0,
                'average_order_value' => $sales->average_order_value ?? 0,
            ]
        );
    }
}