<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetBillPayment extends Model
{
    protected $table = 'ret_bill_payments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'receipt_number',
        'receipt_sequence',
        'customer_id',
        'bill_type',
        'biller_code',
        'bill_amount_minor',
        'admin_fee_minor',
        'total_paid_minor',
        'status',
    ];

    protected $casts = [
        'receipt_sequence' => 'integer',
        'bill_amount_minor' => 'integer',
        'admin_fee_minor' => 'integer',
        'total_paid_minor' => 'integer',
    ];
}
