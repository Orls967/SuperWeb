<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingCalendar extends Model
{
    protected $table = 'mfg_working_calendars';

    protected $fillable = ['plant_id', 'calendar_date', 'is_working_day', 'reason', 'overtime_allowed'];

    protected $casts = ['calendar_date' => 'date', 'is_working_day' => 'boolean', 'overtime_allowed' => 'boolean'];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
