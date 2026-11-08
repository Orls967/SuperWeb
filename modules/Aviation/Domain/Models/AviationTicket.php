<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Aviation\Domain\Enums\TicketStatus;
use Modules\Aviation\Domain\StateMachines\TicketStateMachine;

class AviationTicket extends Model
{
    protected $table = 'avi_tickets';

    protected $fillable = [
        'ticket_number',
        'flight_id',
        'seat_hold_id',
        'passenger_party_id',
        'cabin',
        'seat_assignment',
        'fare_basis',
        'base_fare_minor',
        'tax_minor',
        'total_fare_minor',
        'currency',
        'status',
        'is_baggage_checked',
        'baggage_weight_g',
        'pii_name_encrypted',
        'pii_blind_index',
    ];

    protected $casts = [
        'base_fare_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_fare_minor' => 'integer',
        'is_baggage_checked' => 'boolean',
        'baggage_weight_g' => 'integer',
        'status' => 'string',
        'pii_name_encrypted' => 'encrypted',
    ];

    public function getPassengerNameAttribute(): string
    {
        return (string) ($this->pii_name_encrypted ?? 'Passenger '.$this->id);
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'flight_id');
    }

    public function seatHold(): BelongsTo
    {
        return $this->belongsTo(AviationSeatHold::class, 'seat_hold_id');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(AviationRefund::class, 'ticket_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AviationTicketTransition::class, 'ticket_id');
    }

    public function transitionTo(TicketStatus|string $newStatus, string $trigger, ?int $actorId = null, ?string $reason = null): void
    {
        $newStatusVal = is_string($newStatus) ? $newStatus : $newStatus->value;
        TicketStateMachine::assertCanTransition($this->status, $newStatusVal);

        $oldStatus = $this->status;
        $this->status = $newStatusVal;
        $this->save();

        $this->transitions()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatusVal,
            'trigger' => $trigger,
            'actor_id' => $actorId,
            'reason' => $reason,
        ]);
    }
}
