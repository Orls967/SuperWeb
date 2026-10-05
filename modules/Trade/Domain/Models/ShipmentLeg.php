<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShipmentLeg extends Model
{
    use HasUuids;

    protected $table = 'trd_shipment_legs';

    protected $fillable = [
        'order_reference_no',
        'leg_stage',
        'location_name',
        'notes',
        'previous_hash',
        'hash',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}
