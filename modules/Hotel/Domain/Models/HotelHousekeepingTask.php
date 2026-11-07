<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HotelHousekeepingTask extends Model
{
    protected $table = 'htl_housekeeping_tasks';

    protected $fillable = [
        'property_id',
        'room_id',
        'assigned_user_id',
        'task_date',
        'credit_points',
        'is_priority',
        'is_vip',
        'route_sequence',
        'inspection_score',
        'inspection_notes',
        'status',
        'started_at',
        'completed_at',
        'inspected_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'task_date' => 'date',
        'credit_points' => 'integer',
        'is_priority' => 'boolean',
        'is_vip' => 'boolean',
        'route_sequence' => 'integer',
        'inspection_score' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'inspected_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'room_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
