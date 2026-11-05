<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SybBmtRepayment extends Model
{
    protected $table = 'syb_bmt_repayments';

    protected $fillable = [
        'repayment_code',
        'facility_code',
        'payer_party_ref',
        'payment_amount_idr',
        'allocated_margin_idr',
        'allocated_principal_idr',
        'allocated_tazir_idr',
        'excess_deposit_idr',
        'certificate_hash',
        'paid_at',
    ];

    protected $casts = [
        'payment_amount_idr' => 'integer',
        'allocated_margin_idr' => 'integer',
        'allocated_principal_idr' => 'integer',
        'allocated_tazir_idr' => 'integer',
        'excess_deposit_idr' => 'integer',
        'paid_at' => 'datetime',
    ];
}
