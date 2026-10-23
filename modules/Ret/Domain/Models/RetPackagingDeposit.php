<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetPackagingDeposit extends Model
{
    protected $table = 'ret_packaging_deposits';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'deposit_code',
        'customer_id',
        'packaging_type',
        'deposit_amount_minor',
        'status',
    ];

    protected $casts = [
        'deposit_amount_minor' => 'integer',
    ];
}
