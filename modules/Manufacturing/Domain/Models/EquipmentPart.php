<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Suku cadang pabrik per work center: BOM peralatan + stok minimum. */
class EquipmentPart extends Model
{
    protected $table = 'mfg_equipment_parts';

    protected $fillable = [
        'work_center_id', 'part_code', 'name', 'qty_per_equipment', 'min_stock', 'unit_cost_idr',
    ];

    protected $casts = [
        'qty_per_equipment' => 'integer', 'min_stock' => 'decimal:6', 'unit_cost_idr' => 'integer',
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function isBelowMinimum(float $stockOnHand): bool
    {
        return $stockOnHand < (float) $this->min_stock;
    }
}
