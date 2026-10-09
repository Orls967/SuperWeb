<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IotDevice extends Model
{
    protected $table = 'tlx_iot_devices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'data_quota_mb_monthly' => 'float',
        'consumed_mb_monthly' => 'float',
        'rate_per_mb_minor' => 'integer',
    ];
}
