<?php

return [
    // For daily sales summaries
    'daily_sales' => [
        'cache_ttl' => env('DAILY_SALES_CACHE_TTL', 3600), // 1 hour
    ],

    // For top products report
    'immediate_threshold_days' => env('REPORTS_IMMEDIATE_THRESHOLD_DAYS', 7),
    'default_range' => env('REPORTS_DEFAULT_RANGE', 7),

    // Email settings
    'email' => [
        'from_address' => env('REPORTS_FROM_EMAIL', 'reports@example.com'),
        'from_name' => env('REPORTS_FROM_NAME', 'Sales Reports'),
    ],
];