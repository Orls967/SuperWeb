<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AviationDisruptionPassenger extends Model
{
    protected $table = 'avi_disruption_passengers';

    protected $fillable = [
        'disruption_case_id',
        'ticket_id',
        'passenger_name',
        'priority_score',
        'loyalty_tier',
        'special_service_request',
        'reaccommodation_status',
        'new_flight_id',
        'new_seat_number',
        'hotel_reservation_ref',
    ];

    protected $casts = [
        'priority_score' => 'integer',
    ];

    public function disruptionCase(): BelongsTo
    {
        return $this->belongsTo(AviationDisruptionCase::class, 'disruption_case_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(AviationTicket::class, 'ticket_id');
    }

    public function newFlight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'new_flight_id');
    }

    public function careVouchers(): HasMany
    {
        return $this->hasMany(AviationCareVoucher::class, 'disruption_passenger_id');
    }
}
