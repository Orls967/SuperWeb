<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BankGuarantee extends Model
{
    use HasUuids;

    protected $table = 'tf_bank_guarantees';

    protected $fillable = [
        'guarantee_number',
        'type',
        'issuing_bank',
        'applicant_name',
        'beneficiary_name',
        'amount_idr',
        'effective_date',
        'expiry_date',
        'claim_amount_idr',
        'status',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
        'claim_amount_idr' => 'integer',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];
}
