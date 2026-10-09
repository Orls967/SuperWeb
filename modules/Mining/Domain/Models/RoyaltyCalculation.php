<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RoyaltyCalculation extends Model
{
    protected $table = 'min_royalty_calculations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'period',
        'total_production_ton',
        'commodity_benchmark_price_idr',
        'royalty_rate_percentage',
        'royalty_due_idr',
        'status',
    ];

    protected $casts = [
        'total_production_ton' => 'decimal:2',
        'commodity_benchmark_price_idr' => 'integer',
        'royalty_rate_percentage' => 'decimal:2',
        'royalty_due_idr' => 'integer',
    ];
}
