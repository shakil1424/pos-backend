<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySalesSummary extends Model
{
    use HasFactory;
    protected $fillable = [
        'tenant_id',
        'date',
        'total_orders',
        'total_sales',
        'average_order_value',
    ];

    protected $casts = [
        'date' => 'date',
        'total_sales' => 'float',
        'average_order_value' => 'float',
    ];
}