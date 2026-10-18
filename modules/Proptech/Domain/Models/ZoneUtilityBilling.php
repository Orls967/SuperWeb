<?php

declare(strict_types=1);

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneUtilityBilling extends Model
{
    protected $table = 'prp_zone_utility_billings';

    protected $fillable = [
        'billing_code',
        'zone_id',
        'tenant_id',
        'billing_date',
        'total_kwh',
        'overtime_ac_hours',
        'overtime_ac_amount_idr',
        'base_amount_idr',
        'total_amount_idr',
        'carbon_emission_kg',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'billing_date' => 'date',
        'total_kwh' => 'decimal:2',
        'overtime_ac_hours' => 'integer',
        'overtime_ac_amount_idr' => 'integer',
        'base_amount_idr' => 'integer',
        'total_amount_idr' => 'integer',
        'carbon_emission_kg' => 'decimal:2',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }
}
