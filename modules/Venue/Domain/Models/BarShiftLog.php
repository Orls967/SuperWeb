<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarShiftLog extends Model
{
    protected $table = 'ven_bar_shift_logs';

    protected $fillable = [
        'venue_id',
        'zone_id',
        'shift_code',
        'start_time',
        'end_time',
        'opening_bottles',
        'closing_bottles',
        'pos_sales_bottles',
        'spillage_ml',
        'variance_bottles',
        'supervisor_user_id',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'opening_bottles' => 'integer',
        'closing_bottles' => 'integer',
        'pos_sales_bottles' => 'integer',
        'spillage_ml' => 'integer',
        'variance_bottles' => 'integer',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }
}
