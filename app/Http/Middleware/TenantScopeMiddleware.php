<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('tenant')) {
            $tenantId = $request->tenant->id;

            // Apply tenant scope to all queries
            Product::addGlobalScope('tenant', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            });

            Customer::addGlobalScope('tenant', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            });

            Order::addGlobalScope('tenant', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            });
        }

        return $next($request);
    }
}