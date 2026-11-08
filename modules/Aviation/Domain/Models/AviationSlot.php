<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationSlot extends Model
{
    protected $table = 'avi_slots';

    protected $fillable = [
        'airport_id',
        'operator_id',
        'season_code',
        'day_of_week',
        'time_window_start',
        'time_window_end',
        'is_historic',
        'usage_rate_bps',
        'status',
    ];

    protected $casts = [
        'is_historic' => 'boolean',
        'day_of_week' => 'integer',
        'usage_rate_bps' => 'integer',
    ];

    public function airport(): BelongsTo
    {
        return $this->belongsTo(AviationAirport::class, 'airport_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(AviationOperator::class, 'operator_id');
    }
}
