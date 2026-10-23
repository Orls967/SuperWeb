<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models\Reverse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReverseOrder extends Model
{
    protected $table = 'lgx_reverse_orders';

    protected $fillable = [
        'reverse_code',
        'event_trigger_type',
        'source_reference_id',
        'pickup_location',
        'destination_facility',
        'status',
        'manifest_number',
        'custody_hash',
        'total_appraised_value_idr',
        'avoided_emissions_kg_co2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReverseItem::class, 'reverse_order_id');
    }
}
