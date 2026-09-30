<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySummary extends Model
{
    protected $table = 'resto_daily_summaries';

    protected $fillable = [
        'outlet_id',
        'date',
        'gross_sales',
        'discount',
        'pb1',
        'net_sales',
        'cogs',
        'waste_value',
        'gross_margin',
        'transactions',
        'guests',
        'avg_check',
        'cash_variance',
        'top_items',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_sales' => 'integer',
        'discount' => 'integer',
        'pb1' => 'integer',
        'net_sales' => 'integer',
        'cogs' => 'integer',
        'waste_value' => 'integer',
        'gross_margin' => 'integer',
        'transactions' => 'integer',
        'guests' => 'integer',
        'avg_check' => 'integer',
        'cash_variance' => 'integer',
        'top_items' => 'array',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
