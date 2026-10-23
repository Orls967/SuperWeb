<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasUuids;

    protected $table = 'hcm_attendance_logs';

    protected $fillable = [
        'employee_id',
        'shift_id',
        'log_date',
        'clock_in',
        'clock_out',
        'late_minutes',
        'overtime_hours',
        'status',
    ];

    protected $casts = [
        'log_date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'late_minutes' => 'integer',
        'overtime_hours' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_id');
    }
}
