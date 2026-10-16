<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IspSubscription extends Model
{
    protected $table = 'tlx_isp_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'subscription_code',
        'customer_id',
        'plan_type',
        'speed_mbps',
        'monthly_usage_cap_gb',
        'current_usage_gb',
        'monthly_fee_minor',
        'billing_type',
        'service_status',
        'is_overdue',
    ];

    protected $casts = [
        'speed_mbps' => 'float',
        'monthly_usage_cap_gb' => 'float',
        'current_usage_gb' => 'float',
        'monthly_fee_minor' => 'integer',
        'is_overdue' => 'boolean',
    ];
}
