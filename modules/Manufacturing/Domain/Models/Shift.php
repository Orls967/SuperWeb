<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $table = 'mfg_shifts';

    protected $fillable = [
        'plant_id', 'area_id', 'code', 'name', 'starts_at', 'ends_at',
        'break_minutes', 'capacity_units', 'capacity_uom', 'is_active',
    ];

    protected $casts = ['capacity_units' => 'integer', 'is_active' => 'boolean'];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(PlantArea::class, 'area_id');
    }

    public function workerShifts(): HasMany
    {
        return $this->hasMany(WorkerShift::class, 'shift_id');
    }

    /** Kapasitas efektif per shift setelah istirahat (menit kerja bersih). */
    public function effectiveMinutes(): int
    {
        $start = strtotime($this->starts_at);
        $end = strtotime($this->ends_at);
        $duration = $end > $start ? ($end - $start) / 60 : ((86400 - $start + $end) / 60);

        return max(0, (int) $duration - (int) $this->break_minutes);
    }
}
