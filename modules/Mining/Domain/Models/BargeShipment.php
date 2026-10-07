<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BargeShipment extends Model
{
    protected $table = 'min_barge_shipments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'barge_code',
        'vessel_name',
        'destination_port',
        'manifest_tonnage',
        'weighbridge_tonnage',
        'freight_tariff_idr',
        'custody_hash',
        'status',
    ];
}
