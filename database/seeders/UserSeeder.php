<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant1 = Tenant::where('domain', 'demo1.local')->first();
        $tenant2 = Tenant::where('domain', 'demo2.local')->first();

        // Tenant 1 Users
        User::create([
            'tenant_id' => $tenant1->id,
            'name' => 'John Doe (Owner)',
            'email' => 'owner1@demo.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        User::create([
            'tenant_id' => $tenant1->id,
            'name' => 'Jane Smith (Staff)',
            'email' => 'staff1@demo.com',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);

        // Tenant 2 Users
        User::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Bob Wilson (Owner)',
            'email' => 'owner2@demo.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        User::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Alice Johnson (Staff)',
            'email' => 'staff2@demo.com',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);
    }
}