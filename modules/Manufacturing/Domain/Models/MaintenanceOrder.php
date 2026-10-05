<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Work order pabrik: korektif/preventif/prediktif/alarm sensor. */
class MaintenanceOrder extends Model
{
    use HasUuids;

    protected $table = 'mfg_maintenance_orders';

    protected $fillable = [
        'number', 'work_center_id', 'asset_id', 'kind', 'status', 'priority',
        'trigger_key', 'description', 'due_date', 'started_at', 'completed_at',
        'labor_cost_idr', 'parts_cost_idr', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'asset_id' => 'string', 'due_date' => 'date',
        'started_at' => 'datetime', 'completed_at' => 'datetime',
        'labor_cost_idr' => 'integer', 'parts_cost_idr' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MaintenancePart::class, 'maintenance_order_id');
    }

    public function canTransitionTo(string $to): bool
    {
        $allowed = [
            'open' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [], 'cancelled' => [],
        ];

        return in_array($to, $allowed[$this->status] ?? [], true);
    }
}
