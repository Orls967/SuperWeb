<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penugasan pekerja ke shift pada tanggal tertentu. */
class WorkerShift extends Model
{
    protected $table = 'mfg_worker_shifts';

    protected $fillable = [
        'worker_id', 'shift_id', 'work_date', 'status', 'overtime_minutes',
    ];

    protected $casts = [
        'work_date' => 'date',
        'overtime_minutes' => 'integer',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
