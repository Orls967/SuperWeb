<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchAssignment extends LogisticsEntity
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RELEASED = 'released';

    protected $table = 'lgx_dispatch_assignments';

    protected $fillable = [
        'schedule_id',
        'truck_id',
        'driver_id',
        'assigned_by',
        'trip_minutes',
        'status',
        'release_reason',
        'assigned_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'trip_minutes' => 'integer',
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class, 'truck_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
