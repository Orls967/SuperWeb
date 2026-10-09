<?php

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneUtilityBilling extends Model
{
    protected $table = 'prp_zone_utility_billings';

    protected $guarded = [];

    protected $casts = [
        'billing_date' => 'date',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }
}
