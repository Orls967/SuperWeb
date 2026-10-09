<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IotConnectivityInvoice extends Model
{
    protected $table = 'tlx_iot_connectivity_invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'active_device_count' => 'integer',
        'total_consumed_mb' => 'float',
        'total_charge_minor' => 'integer',
    ];
}
