<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TradeLoan extends Model
{
    use HasUuids;

    protected $table = 'tf_trade_loans';

    protected $fillable = [
        'loan_number',
        'facility_type',
        'borrower_name',
        'principal_amount_idr',
        'interest_rate_percent',
        'disbursed_at',
        'due_date',
        'repaid_amount_idr',
        'status',
    ];

    protected $casts = [
        'principal_amount_idr' => 'integer',
        'interest_rate_percent' => 'float',
        'repaid_amount_idr' => 'integer',
        'disbursed_at' => 'date',
        'due_date' => 'date',
    ];
}
