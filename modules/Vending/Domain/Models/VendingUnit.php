<?php

declare(strict_types=1);

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendingUnit extends Model
{
    protected $table = 'ven_vending_units';

    protected $fillable = [
        'unit_code',
        'location_name',
        'latitude',
        'longitude',
        'status',
        'current_stock',
        'critical_threshold',
        'max_capacity',
        'total_sales_count',
        'total_sales_idr',
        'sensor_status',
    ];

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
