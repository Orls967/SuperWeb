<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybSukukDistribution extends Model
{
    protected $table = 'syb_sukuk_distributions';

    protected $fillable = [
        'sukuk_facility_id',
        'period_code',
        'coupon_pool_idr',
        'distributed_idr',
        'rounding_reserve_idr',
        'distribution_date',
        'idempotency_key',
    ];

    protected $casts = [
        'sukuk_facility_id' => 'integer',
        'coupon_pool_idr' => 'integer',
        'distributed_idr' => 'integer',
        'rounding_reserve_idr' => 'integer',
        'distribution_date' => 'date',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(SybSukukFacility::class, 'sukuk_facility_id');
    }
}
