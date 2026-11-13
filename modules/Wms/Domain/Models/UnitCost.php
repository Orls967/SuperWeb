<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Harga pokok standar / rata-rata berversi per produk & gudang (minor units IDR).
 */
class UnitCost extends Model
{
    protected $table = 'wms_unit_costs';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'cost_idr',
        'method',
        'effective_date',
        'created_by_user_id',
        'is_active',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'cost_idr' => 'integer',
        'effective_date' => 'date',
        'created_by_user_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
