<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::create([
            'name' => 'Demo Business 1',
            'domain' => 'demo1.local',
            'settings' => ['currency' => 'USD', 'timezone' => 'America/New_York'],
            'is_active' => true,
        ]);

        Tenant::create([
            'name' => 'Demo Business 2',
            'domain' => 'demo2.local',
            'settings' => ['currency' => 'EUR', 'timezone' => 'Europe/London'],
            'is_active' => true,
        ]);
    }
}