<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetBundleSubscription extends Model
{
    protected $table = 'ret_bundle_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'subscription_code',
        'bundle_id',
        'customer_id',
        'amount_billed_minor',
        'billing_period',
        'status',
    ];

    protected $casts = [
        'amount_billed_minor' => 'integer',
    ];
}
