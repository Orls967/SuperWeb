<?php

declare(strict_types=1);

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendingRestockTask extends Model
{
    protected $table = 'vnd_restock_tasks';

    protected $fillable = [
        'task_code',
        'vending_unit_id',
        'suggested_qty',
        'status',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(VendingUnit::class, 'vending_unit_id');
    }
}
