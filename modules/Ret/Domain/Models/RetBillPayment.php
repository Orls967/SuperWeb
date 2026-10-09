<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetBillPayment extends Model
{
    protected $table = 'ret_bill_payments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'receipt_sequence' => 'integer',
        'bill_amount_minor' => 'integer',
        'admin_fee_minor' => 'integer',
        'total_paid_minor' => 'integer',
    ];
}
