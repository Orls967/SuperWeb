<?php

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendingUnit extends Model
{
    protected $table = 'ven_vending_units';

    protected $guarded = [];

    protected $casts = [
        'sensor_status' => 'array',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(VendingTransaction::class, 'vending_unit_id');
    }

    public function restockTasks(): HasMany
    {
        return $this->hasMany(VendingRestockTask::class, 'vending_unit_id');
    }
}
