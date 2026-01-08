<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with(['customer', 'items.product'])
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->has('customer_id'), function ($query) use ($request) {
                $query->where('customer_id', $request->customer_id);
            })
            ->when($request->has('start_date'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->start_date);
            })
            ->when($request->has('end_date'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->end_date);
            })
            ->orderBy('created_at', 'desc');

        $orders = $query->paginate($request->per_page ?? 15);

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request) {
            $tenantId = $request->header('X-Tenant-ID');
            $totalAmount = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $unitPrice = $product->price;
                $itemTotal = $unitPrice * $item['quantity'];

                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $itemTotal,
                ];

                $totalAmount += $itemTotal;

                $product->decrement('stock_quantity', $item['quantity']);
            }

            $order = Order::create([
                'tenant_id' => $tenantId,
                'customer_id' => $request->customer_id,
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Order created successfully',
            'order' => new OrderResource($order->load(['customer', 'items.product'])),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json([
            'order' => new OrderResource($order->load(['customer', 'items.product'])),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        if ($order->status === 'pending') {
            $order->update($request->only('notes'));
        }

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => new OrderResource($order->fresh()->load(['customer', 'items.product'])),
        ]);
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending orders can be deleted'
            ], 422);
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $item->product->increment('stock_quantity', $item->quantity);
            }
            $order->delete();
        });

        return response()->json([
            'message' => 'Order deleted successfully'
        ]);
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $order = DB::transaction(function () use ($order) {
            if ($order->status !== 'cancelled') {
                foreach ($order->items as $item) {
                    $item->product->increment('stock_quantity', $item->quantity);
                }

                $order->markAsCancelled();
            }

            return $order->fresh()->load(['customer', 'items.product']);
        });

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => new OrderResource($order),
        ]);
    }

    public function markAsPaid(Order $order): JsonResponse
    {
        $this->authorize('markAsPaid', $order);

        $order->markAsPaid();

        return response()->json([
            'message' => 'Order marked as paid',
            'order' => new OrderResource($order->fresh()->load(['customer', 'items.product'])),
        ]);
    }
}