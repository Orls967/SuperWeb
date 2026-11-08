<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetBunkerAdjustment extends Model
{
    protected $table = 'prt_fleet_bunker_adjustments';

    protected $fillable = [
        'charter_party_id',
        'event_type',
        'vlsfo_quantity_mt',
        'lsmgo_quantity_mt',
        'vlsfo_price_per_mt',
        'lsmgo_price_per_mt',
        'total_value_usd',
        'surveyor_company',
        'certificate_ref',
    ];

    protected $casts = [
        'vlsfo_quantity_mt' => 'float',
        'lsmgo_quantity_mt' => 'float',
        'vlsfo_price_per_mt' => 'float',
        'lsmgo_price_per_mt' => 'float',
        'total_value_usd' => 'float',
    ];

    public function charterParty(): BelongsTo
    {
        return $this->belongsTo(FleetCharterParty::class, 'charter_party_id');
    }
}
