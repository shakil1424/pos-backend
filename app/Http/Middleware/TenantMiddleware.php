<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant ID is required in X-Tenant-ID header',
                'error' => 'tenant_id_missing'
            ], 400);
        }

        $tenant = Tenant::where('id', $tenantId)->where('is_active', true)->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Invalid or inactive tenant',
                'error' => 'tenant_invalid'
            ], 403);
        }

        // Set tenant in request for easy access
        $request->merge(['tenant' => $tenant]);

        // Set tenant ID in config for global access
        config(['tenant.id' => $tenant->id]);

        return $next($request);
    }
}