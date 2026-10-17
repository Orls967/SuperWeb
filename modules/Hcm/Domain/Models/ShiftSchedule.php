<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftSchedule extends Model
{
    use HasUuids;

    protected $table = 'hcm_shift_schedules';

    protected $fillable = [
        'shift_code',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'is_night_shift',
    ];

    protected $casts = [
        'break_minutes' => 'integer',
        'is_night_shift' => 'boolean',
    ];

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'shift_id');
    }
}
