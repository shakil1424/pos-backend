<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('order'));
    }

    public function rules(): array
    {
        $order = $this->route('order');

        return [
            'customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where(function ($query) use ($order) {
                    $query->where('tenant_id', $order->tenant_id);
                }),
            ],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => [
                'required_with:items',
                Rule::exists('products', 'id')->where(function ($query) use ($order) {
                    $query->where('tenant_id', $order->tenant_id)
                        ->where('is_active', true);
                }),
            ],
            'items.*.quantity' => ['required_with:items.*.product_id', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'processing', 'completed', 'cancelled'])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');

            if ($this->has('customer_id') && $this->customer_id != $order->customer_id) {
                $customer = Customer::where('id', $this->customer_id)
                    ->where('tenant_id', $order->tenant_id)
                    ->first();

                if (!$customer) {
                    $validator->errors()->add(
                        'customer_id',
                        'Customer not found in your business.'
                    );
                }
            }

            if ($this->has('items')) {
                foreach ($this->items as $index => $item) {
                    $product = \App\Models\Product::where('id', $item['product_id'])
                        ->where('tenant_id', $order->tenant_id)
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

            if ($order->status !== 'pending') {
                if ($this->has('items')) {
                    $validator->errors()->add(
                        'items',
                        'Order items cannot be updated once the order is no longer pending.'
                    );
                }

                if ($this->has('customer_id') && $this->customer_id != $order->customer_id) {
                    $validator->errors()->add(
                        'customer_id',
                        'Customer cannot be changed once the order is no longer pending.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.exists' => 'The selected product is invalid or not available in your business.',
            'status.in' => 'The status must be one of: pending, processing, completed, cancelled.',
        ];
    }
}