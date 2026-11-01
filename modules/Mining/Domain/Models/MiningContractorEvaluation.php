<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningContractorEvaluation extends Model
{
    protected $table = 'min_contractor_evaluations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'evaluation_uuid',
        'contractor_party_id',
        'site_id',
        'period',
        'total_ton_km',
        'fuel_consumed_liters',
        'sfc_per_100_ton_km',
        'baseline_median',
        'baseline_mad',
        'is_anomaly',
        'penalty_amount_idr',
        'payment_held',
    ];

    protected $casts = [
        'total_ton_km' => 'integer',
        'fuel_consumed_liters' => 'integer',
        'sfc_per_100_ton_km' => 'decimal:2',
        'baseline_median' => 'decimal:2',
        'baseline_mad' => 'decimal:2',
        'is_anomaly' => 'boolean',
        'penalty_amount_idr' => 'integer',
        'payment_held' => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }
}
