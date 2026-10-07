<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetFulfillmentSplit extends Model
{
    protected $table = 'ret_fulfillment_splits';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
    ];
}
