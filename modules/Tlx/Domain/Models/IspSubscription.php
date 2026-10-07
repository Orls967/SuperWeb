<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IspSubscription extends Model
{
    protected $table = 'tlx_isp_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'speed_mbps' => 'float',
        'monthly_usage_cap_gb' => 'float',
        'current_usage_gb' => 'float',
        'monthly_fee_minor' => 'integer',
        'is_overdue' => 'boolean',
    ];
}
