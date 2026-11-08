<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationSeatInventory extends Model
{
    protected $table = 'avi_seat_inventories';

    protected $fillable = [
        'flight_id',
        'cabin',
        'total_capacity',
        'held_seats',
        'booked_seats',
        'oversell_limit_seats',
    ];

    protected $casts = [
        'total_capacity' => 'integer',
        'held_seats' => 'integer',
        'booked_seats' => 'integer',
        'oversell_limit_seats' => 'integer',
    ];

    public function flight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'flight_id');
    }

    public function availableSeats(): int
    {
        $maxAllowed = $this->total_capacity + $this->oversell_limit_seats;

        return max(0, $maxAllowed - ($this->held_seats + $this->booked_seats));
    }
}
