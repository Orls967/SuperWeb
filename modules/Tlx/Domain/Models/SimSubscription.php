<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SimSubscription extends Model
{
    protected $table = 'tlx_sim_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'msisdn',
        'iccid',
        'account_tier',
        'parent_msisdn',
        'wallet_id',
        'plan_code',
        'quota_allowance_gb',
        'quota_remaining_gb',
        'rollover_quota_gb',
        'auto_renew_fee_minor',
        'status',
    ];

    protected $casts = [
        'quota_allowance_gb' => 'float',
        'quota_remaining_gb' => 'float',
        'rollover_quota_gb' => 'float',
        'auto_renew_fee_minor' => 'integer',
    ];
}
