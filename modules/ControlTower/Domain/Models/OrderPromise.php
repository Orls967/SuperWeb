<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPromise extends Model
{
    protected $table = 'sct_order_promises';

    protected $fillable = [
        'promise_code',
        'order_reference',
        'item_code',
        'requested_qty',
        'atp_confirmed_qty',
        'ctp_manufacturing_qty',
        'promised_delivery_date',
        'promise_status',
        'idempotency_key',
        'payload_hash',
        'promised_period',
        'late_periods',
        'reason_code',
        'certificate_hash',
        'as_of_date',
        'atp_cumulative',
        'ctp_cumulative',
    ];

    protected $casts = [
        'requested_qty' => 'integer',
        'atp_confirmed_qty' => 'integer',
        'ctp_manufacturing_qty' => 'integer',
        'promised_delivery_date' => 'date',
        'promised_period' => 'integer',
        'late_periods' => 'integer',
        'as_of_date' => 'date',
        'atp_cumulative' => 'integer',
        'ctp_cumulative' => 'integer',
    ];
}
