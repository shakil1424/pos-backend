<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Both owner and staff can view products
    }

    public function view(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id && $user->isOwner();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id && $user->isOwner();
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id && $user->isOwner();
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id && $user->isOwner();
    }
}