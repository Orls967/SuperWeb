<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetCrowdshippingTask extends Model
{
    protected $table = 'ret_crowdshipping_tasks';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'task_code',
        'order_code',
        'crowd_driver_id',
        'order_value_minor',
        'delivery_fee_minor',
        'pod_hash',
        'status',
    ];

    protected $casts = [
        'order_value_minor' => 'integer',
        'delivery_fee_minor' => 'integer',
    ];
}
