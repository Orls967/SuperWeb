<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetFulfillmentSplit extends Model
{
    protected $table = 'ret_fulfillment_splits';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'split_code',
        'order_id',
        'sku',
        'quantity',
        'fulfillment_location_type',
        'facility_id',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];
}
