<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Suku cadang yang dipakai oleh suatu work order pemeliharaan. */
class MaintenancePart extends Model
{
    protected $table = 'mfg_maintenance_parts';

    protected $fillable = ['maintenance_order_id', 'equipment_part_id', 'qty', 'cost_idr'];

    protected $casts = ['qty' => 'decimal:6', 'cost_idr' => 'integer'];

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function equipmentPart(): BelongsTo
    {
        return $this->belongsTo(EquipmentPart::class, 'equipment_part_id');
    }
}
