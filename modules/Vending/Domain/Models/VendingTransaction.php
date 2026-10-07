<?php

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendingTransaction extends Model
{
    protected $table = 'ven_vending_transactions';

    protected $guarded = [];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(VendingUnit::class, 'vending_unit_id');
    }
}
