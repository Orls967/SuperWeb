<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Aviation\Domain\Enums\FlightStatus;
use Modules\Aviation\Domain\StateMachines\FlightStateMachine;

class AviationFlight extends Model
{
    protected $table = 'avi_flights';

    protected $fillable = [
        'flight_number',
        'operator_id',
        'aircraft_id',
        'route_id',
        'origin_airport_id',
        'destination_airport_id',
        'departure_time',
        'arrival_time',
        'departure_slot_id',
        'arrival_slot_id',
        'status',
        'actual_departure',
        'actual_arrival',
        'fuel_burn_kg',
        'load_sheet_id',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'actual_departure' => 'datetime',
        'actual_arrival' => 'datetime',
        'fuel_burn_kg' => 'integer',
        'status' => 'string',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(AviationOperator::class, 'operator_id');
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(AviationAircraft::class, 'aircraft_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(AviationRoute::class, 'route_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(AviationAirport::class, 'origin_airport_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(AviationAirport::class, 'destination_airport_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(AviationSeatInventory::class, 'flight_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(AviationTicket::class, 'flight_id');
    }

    public function cargoConsignments(): HasMany
    {
        return $this->hasMany(AviationCargoConsignment::class, 'flight_id');
    }

    public function weightBalanceRecord(): HasOne
    {
        return $this->hasOne(AviationWeightBalanceRecord::class, 'flight_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AviationFlightTransition::class, 'flight_id');
    }

    public function transitionTo(FlightStatus|string $newStatus, string $trigger, ?int $actorId = null, ?string $notes = null): void
    {
        $targetStatus = is_string($newStatus) ? $newStatus : $newStatus->value;
        FlightStateMachine::assertCanTransition($this->status, $targetStatus);

        $previousStatus = $this->status;
        $this->status = $targetStatus;

        // Catat timestamp penerbangan bila berangkat
        if ($targetStatus === FlightStatus::DEPARTED->value && empty($this->actual_departure)) {
            $this->actual_departure = now();
        }

        $this->save();

        $this->transitions()->create([
            'flight_id' => $this->id,
            'from_status' => $previousStatus,
            'to_status' => $targetStatus,
            'trigger' => $trigger,
            'actor_id' => $actorId,
            'notes' => $notes,
        ]);
    }
}

