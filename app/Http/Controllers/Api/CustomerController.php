<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc');

        $customers = $query->paginate($request->per_page ?? 15);

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create(array_merge(
            $request->validated(),
            ['tenant_id' => $request->header('X-Tenant-ID')]
        ));

        return response()->json([
            'message' => 'Customer created successfully',
            'customer' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json([
            'customer' => new CustomerResource($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json([
            'message' => 'Customer updated successfully',
            'customer' => new CustomerResource($customer->fresh()),
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        // Check if customer has orders
        if ($customer->orders()->exists()) {
            return response()->json([
                'message' => 'Cannot delete customer with existing orders. Consider archiving instead.'
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully'
        ]);
    }

    public function restore($id): JsonResponse
    {
        $customer = Customer::withTrashed()->findOrFail($id);

        $this->authorize('restore', $customer);

        $customer->restore();

        return response()->json([
            'message' => 'Customer restored successfully',
            'customer' => new CustomerResource($customer),
        ]);
    }
}