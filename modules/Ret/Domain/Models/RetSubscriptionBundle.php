<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetSubscriptionBundle extends Model
{
    protected $table = 'ret_subscription_bundles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'monthly_price_minor' => 'integer',
        'line_settlement_breakdown' => 'array',
    ];
}
