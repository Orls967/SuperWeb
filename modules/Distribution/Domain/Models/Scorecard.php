<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Scorecard kinerja distributor per periode (42.8). */
class Scorecard extends Model
{
    protected $table = 'dist_scorecards';

    protected $fillable = [
        'distributor_id', 'period', 'sell_in_idr', 'sell_out_idr', 'fill_rate_percent',
        'dso_days', 'price_compliance_percent', 'achievement_percent', 'score', 'recommended_tier',
    ];

    protected $casts = [
        'sell_in_idr' => 'integer', 'sell_out_idr' => 'integer',
        'fill_rate_percent' => 'decimal:4', 'dso_days' => 'decimal:2',
        'price_compliance_percent' => 'decimal:4', 'achievement_percent' => 'decimal:4',
        'score' => 'decimal:4',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }
}
