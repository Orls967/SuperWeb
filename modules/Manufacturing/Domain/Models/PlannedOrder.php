<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Planned production/purchase order; becomes firm explicitly. */
class PlannedOrder extends Model
{
    use HasUuids;

    protected $table = 'mfg_planned_orders';

    protected $fillable = [
        'run_id', 'material_id', 'kind', 'qty', 'release_date', 'due_date',
        'status', 'reservation_kind', 'pr_ref', 'production_order_id', 'superseded_by_run_id',
    ];

    protected $casts = [
        'qty' => 'decimal:6', 'release_date' => 'date', 'due_date' => 'date',
        'production_order_id' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class, 'run_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(MaterialReservation::class, 'planned_order_id');
    }
}
