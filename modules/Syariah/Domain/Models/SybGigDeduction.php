<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SybGigDeduction extends Model
{
    protected $table = 'syb_gig_deductions';

    protected $fillable = [
        'deduction_code',
        'loan_code',
        'payout_ref',
        'deducted_amount_idr',
        'allocated_margin_idr',
        'allocated_principal_idr',
        'allocated_tazir_idr',
        'deducted_at',
    ];

    protected $casts = [
        'deducted_amount_idr' => 'integer',
        'allocated_margin_idr' => 'integer',
        'allocated_principal_idr' => 'integer',
        'allocated_tazir_idr' => 'integer',
        'deducted_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(SybGigMicroloan::class, 'loan_code', 'loan_code');
    }
}

