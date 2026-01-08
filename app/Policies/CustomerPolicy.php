<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id;
    }

    public function create(User $user): bool
    {
        return true; // Both can create customers
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id && $user->isOwner();
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id && $user->isOwner();
    }
}