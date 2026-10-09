<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CreditFacility extends Model
{
    use HasUuids;

    protected $table = 'trs_credit_facilities';

    protected $fillable = [
        'facility_code',
        'bank_name',
        'credit_limit_idr',
        'drawn_amount_idr',
        'interest_rate_percent',
        'max_debt_equity_ratio',
        'status',
    ];

    protected $casts = [
        'credit_limit_idr' => 'integer',
        'drawn_amount_idr' => 'integer',
        'interest_rate_percent' => 'float',
        'max_debt_equity_ratio' => 'float',
    ];
}
