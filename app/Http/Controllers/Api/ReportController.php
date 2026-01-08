<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\DailySalesSummary;
use App\Jobs\SendTopProductsEmailJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct()
    {
    }

    public function dailySales(ReportRequest $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $date = $request->input('date');

        $summary = DailySalesSummary::where('tenant_id', $tenantId)
            ->whereDate('date', $date)
            ->first();

        if (!$summary) {
            $sales = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('AVG(total_amount) as average_order_value')
            )
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', $date)
                ->where('status', 'paid')
                ->groupBy('date')
                ->first();

            $summary = [
                'date' => $date,
                'total_orders' => $sales->total_orders ?? 0,
                'total_sales' => $sales->total_sales ?? 0,
                'average_order_value' => $sales->average_order_value ?? 0,
                'source' => 'on-demand'
            ];
        } else {
            $summary = $summary->toArray();
            $summary['source'] = 'pre-generated';
        }

        return response()->json([
            'date' => $date,
            'summary' => $summary
        ]);
    }

    public function topProducts(ReportRequest $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $daysDifference = $end->diffInDays($start);

        $immediateThreshold = config('reports.immediate_threshold_days', 7);

        if ($daysDifference <= $immediateThreshold) {

            $topProducts = OrderItem::select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('AVG(order_items.unit_price) as average_price')
            )
                ->join('orders', function ($join) use ($tenantId) {
                    $join->on('order_items.order_id', '=', 'orders.id')
                        ->where('orders.tenant_id', $tenantId);
                })
                ->join('products', function ($join) use ($tenantId) {
                    $join->on('order_items.product_id', '=', 'products.id')
                        ->where('products.tenant_id', $tenantId);
                })
                ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('orders.status', 'paid')
                ->groupBy('products.id', 'products.name', 'products.sku')
                ->orderBy('total_quantity', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'generated' => 'immediate',
                'days_processed' => $daysDifference,
                'top_products' => $topProducts
            ]);
        } else {
            $user = Auth::user();
            $email = $user->email;

            SendTopProductsEmailJob::dispatch(
                $tenantId,
                $startDate,
                $endDate,
                $email
            );

            return response()->json([
                'message' => 'Report generation queued for email delivery',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_processed' => $daysDifference,
                'email' => $email,
                'estimated_time' => '5-10 minutes',
                'note' => "Reports exceeding {$immediateThreshold} days are sent via email"
            ]);
        }
    }

    public function lowStock(ReportRequest $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');

        $lowStockProducts = Product::select('id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold', 'price')
            ->whereRaw('stock_quantity <= low_stock_threshold')
            ->where('is_active', true)
            ->where('tenant_id', $tenantId)
            ->orderBy('stock_quantity', 'asc')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => $product->stock_quantity,
                    'low_stock_threshold' => $product->low_stock_threshold,
                    'price' => (float) $product->price,
                    'needs_restocking' => $product->stock_quantity == 0,
                ];
            });

        return response()->json([
            'generated' => 'immediate',
            'timestamp' => now()->toDateTimeString(),
            'low_stock_products' => $lowStockProducts,
            'count' => $lowStockProducts->count(),
        ]);
    }
}