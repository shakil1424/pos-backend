<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Order::class);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('items')) {
                $tenantId = $this->header('X-Tenant-ID');

                foreach ($this->items as $index => $item) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if (!$product) {
                        $validator->errors()->add(
                            "items.{$index}.product_id",
                            'Product not found in your business.'
                        );
                        continue;
                    }

                    if (!$product->is_active) {
                        $validator->errors()->add(
                            "items.{$index}.product_id",
                            'Product is not active.'
                        );
                    }

                    if (!$product->hasSufficientStock($item['quantity'])) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Insufficient stock for '{$product->name}'. Available: {$product->stock_quantity}"
                        );
                    }
                }
            }
        });
    }
}