<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IotConnectivityInvoice extends Model
{
    use HasUuids;

    protected $table = 'tlx_iot_connectivity_invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'invoice_number',
        'owner_entity_id',
        'billing_period',
        'active_device_count',
        'total_device_fee_minor',
        'total_quota_mb',
        'total_consumed_mb',
        'excess_mb',
        'excess_charge_minor',
        'total_charge_minor',
        'intercompany_transaction_id',
        'ledger_transaction_id',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'active_device_count' => 'integer',
        'total_device_fee_minor' => 'integer',
        'total_quota_mb' => 'integer',
        'total_consumed_mb' => 'float',
        'excess_mb' => 'integer',
        'excess_charge_minor' => 'integer',
        'total_charge_minor' => 'integer',
    ];
}
