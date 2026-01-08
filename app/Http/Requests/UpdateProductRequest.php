<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->product);
    }

    public function rules(): array
    {
        $tenantId = $this->header('X-Tenant-ID');
        $productId = $this->route('product')->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })->ignore($productId)
            ],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:9999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}