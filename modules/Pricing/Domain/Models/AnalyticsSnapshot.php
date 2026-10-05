<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Analitik harga per bulan & channel (44.7). */
class AnalyticsSnapshot extends Model
{
    protected $table = 'pric_analytics_snapshots';

    protected $fillable = [
        'period', 'channel', 'realized_vs_list_percent', 'discount_leakage_idr',
        'promo_effectiveness_percent', 'avg_discount_percent', 'volume_idr', 'breakdown',
    ];

    protected $casts = [
        'realized_vs_list_percent' => 'decimal:4', 'discount_leakage_idr' => 'decimal:2',
        'promo_effectiveness_percent' => 'decimal:4', 'avg_discount_percent' => 'decimal:4',
        'volume_idr' => 'integer', 'breakdown' => 'array',
    ];
}
