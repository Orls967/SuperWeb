<?php

namespace Modules\B2b\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class B2bEscrowAccount extends Model
{
    use HasUuids;

    protected $table = 'b2b_escrow_accounts';

    protected $fillable = [
        'escrow_number',
        'reference_type',
        'reference_id',
        'buyer_id',
        'seller_id',
        'deposit_amount_idr',
        'released_amount_idr',
        'refunded_amount_idr',
        'status',
        'bast_document_id',
        'released_at',
    ];

    protected $casts = [
        'deposit_amount_idr' => 'integer',
        'released_amount_idr' => 'integer',
        'refunded_amount_idr' => 'integer',
        'released_at' => 'datetime',
    ];
}
