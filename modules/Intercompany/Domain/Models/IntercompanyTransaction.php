<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IntercompanyTransaction extends Model
{
    protected $table = 'ic_transactions';

    protected $fillable = [
        'transaction_code',
        'selling_entity',
        'buying_entity',
        'description',
        'currency',
        'amount_idr',
        'sales_invoice_ref',
        'purchase_bill_ref',
        'status',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
    ];
}
