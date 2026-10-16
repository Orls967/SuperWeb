<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models\Autonomous;

use Illuminate\Database\Eloquent\Model;

class TempBreachHold extends Model
{
    protected $table = 'lgx_temp_breach_holds';

    protected $fillable = [
        'breach_code',
        'shipment_code',
        'carrier_id',
        'recorded_temp_c',
        'max_allowed_temp_c',
        'duration_minutes',
        'hold_amount_idr',
        'hash_proof',
        'status',
    ];
}
