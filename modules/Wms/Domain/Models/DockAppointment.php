<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Janji dok inbound/outbound (41.7). */
class DockAppointment extends Model
{
    protected $table = 'wms_dock_appointments';

    protected $fillable = [
        'warehouse_id', 'direction', 'reference', 'window_start', 'window_end',
        'status', 'carrier', 'notes',
    ];

    protected $casts = ['window_start' => 'datetime', 'window_end' => 'datetime'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function overlaps(DockAppointment $other): bool
    {
        return $this->window_start->lt($other->window_end)
            && $this->window_end->gt($other->window_start);
    }
}
