<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lot material: FIFO (produced_at) / FEFO (expiry) untuk issue. */
class MaterialLot extends Model
{
    use HasUuids;

    protected $table = 'mfg_material_lots';

    protected $fillable = [
        'material_id', 'lot_number', 'qty', 'unit_cost_idr', 'expiry_date', 'produced_at',
        'source_type', 'source_ref', 'status',
    ];

    protected $casts = [
        'qty' => 'decimal:6', 'unit_cost_idr' => 'integer', 'expiry_date' => 'date', 'produced_at' => 'date',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
