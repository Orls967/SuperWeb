<?php

declare(strict_types=1);

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendingTransaction extends Model
{
    protected $table = 'ven_vending_transactions';

    protected $fillable = [
        'tx_code',
        'vending_unit_id',
        'item_code',
        'qty',
        'price_idr',
        'payment_method',
        'biometric_token_hash',
        'status',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(VendingUnit::class, 'vending_unit_id');
    }
}
