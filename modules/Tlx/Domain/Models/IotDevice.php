<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IotDevice extends Model
{
    protected $table = 'tlx_iot_devices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'device_uuid',
        'device_type',
        'owner_entity_id',
        'active_sim_iccid',
        'data_quota_mb_monthly',
        'consumed_mb_monthly',
        'rate_per_mb_minor',
        'status',
    ];

    protected $casts = [
        'data_quota_mb_monthly' => 'float',
        'consumed_mb_monthly' => 'float',
        'rate_per_mb_minor' => 'integer',
    ];
}
