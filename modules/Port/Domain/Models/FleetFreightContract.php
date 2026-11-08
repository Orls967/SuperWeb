<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetFreightContract extends Model
{
    protected $table = 'prt_fleet_freight_contracts';

    protected $fillable = [
        'charter_party_id',
        'contract_number',
        'cargo_description',
        'quantity_mt',
        'tolerance_percentage',
        'freight_rate_per_mt',
        'total_freight_estimate',
        'advance_freight_paid',
        'deadfreight_incurred',
        'bill_of_lading_number',
        'payment_status',
    ];

    protected $casts = [
        'quantity_mt' => 'float',
        'tolerance_percentage' => 'float',
        'freight_rate_per_mt' => 'float',
        'total_freight_estimate' => 'float',
        'advance_freight_paid' => 'float',
        'deadfreight_incurred' => 'float',
    ];

    public function charterParty(): BelongsTo
    {
        return $this->belongsTo(FleetCharterParty::class, 'charter_party_id');
    }

    public function balanceDue(): float
    {
        return ($this->total_freight_estimate + $this->deadfreight_incurred) - $this->advance_freight_paid;
    }
}

