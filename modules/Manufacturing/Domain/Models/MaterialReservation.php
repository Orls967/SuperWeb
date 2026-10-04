<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hard/soft material commitment for a firm planned order. */
class MaterialReservation extends Model
{
    use HasUuids;

    protected $table = 'mfg_material_reservations';

    protected $fillable = [
        'planned_order_id', 'material_id', 'qty', 'kind', 'status', 'shortfall_note',
    ];

    protected $casts = ['qty' => 'decimal:6'];

    public function plannedOrder(): BelongsTo
    {
        return $this->belongsTo(PlannedOrder::class, 'planned_order_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
