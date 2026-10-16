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
        'total_amount_idr',
        'carbon_emission_kg',
        'status',
    ];

    protected $casts = [
        'billing_date' => 'date',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }
}
