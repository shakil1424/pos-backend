<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Jobs\GenerateDailySalesSummaryJob;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateDailySalesSummaries extends Command
{
    protected $signature = 'reports:generate-daily-summaries';
    protected $description = 'Generate daily sales summaries for all tenants';

    public function handle()
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        $tenants = Tenant::active()->get();

        foreach ($tenants as $tenant) {
            GenerateDailySalesSummaryJob::dispatch($tenant->id, $yesterday);
            $this->info("Queued daily summary generation for tenant {$tenant->id} ({$tenant->name})");
        }

        $this->info("Daily sales summary generation queued for {$tenants->count()} tenants.");
    }
}