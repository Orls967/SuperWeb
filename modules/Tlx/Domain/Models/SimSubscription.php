<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SimSubscription extends Model
{
    protected $table = 'tlx_sim_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'quota_allowance_gb' => 'float',
        'quota_remaining_gb' => 'float',
        'rollover_quota_gb' => 'float',
        'auto_renew_fee_minor' => 'integer',
    ];
}
