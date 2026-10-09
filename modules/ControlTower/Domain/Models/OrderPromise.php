<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPromise extends Model
{
    protected $table = 'sct_order_promises';

    protected $guarded = [];

    protected $casts = [
        'requested_qty' => 'integer',
        'atp_confirmed_qty' => 'integer',
        'ctp_manufacturing_qty' => 'integer',
        'promised_delivery_date' => 'date',
    ];
}
