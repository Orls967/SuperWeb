<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BundleOrder extends Model
{
    protected $table = 'ven_bundle_orders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'festival_bundle_id',
        'customer_user_id',
        'order_number',
        'total_paid',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'total_paid' => 'integer',
        'settled_at' => 'datetime',
    ];
}
