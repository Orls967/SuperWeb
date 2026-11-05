<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SybGigMicroloan extends Model
{
    protected $table = 'syb_gig_microloans';

    protected $fillable = [
        'loan_code',
        'worker_party_ref',
        'gross_average_income_idr',
        'income_volatility_bps',
        'amanah_score',
        'approved_credit_limit_idr',
        'disbursed_amount_idr',
        'remaining_balance_idr',
        'monthly_deduction_idr',
        'status',
    ];

    protected $casts = [
        'gross_average_income_idr' => 'integer',
        'income_volatility_bps' => 'integer',
        'amanah_score' => 'integer',
        'approved_credit_limit_idr' => 'integer',
        'disbursed_amount_idr' => 'integer',
        'remaining_balance_idr' => 'integer',
        'monthly_deduction_idr' => 'integer',
    ];

    public function deductions(): HasMany
    {
        return $this->hasMany(SybGigDeduction::class, 'loan_code', 'loan_code');
    }
}
