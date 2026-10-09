<?php

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendingRestockTask extends Model
{
    protected $table = 'ven_restock_tasks';

    protected $guarded = [];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(VendingUnit::class, 'vending_unit_id');
    }
}
