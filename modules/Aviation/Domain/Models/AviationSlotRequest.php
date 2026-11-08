<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Aviation\Domain\Enums\SlotRequestStatus;
use Modules\Aviation\Domain\StateMachines\SlotRequestStateMachine;

class AviationSlotRequest extends Model
{
    protected $table = 'avi_slot_requests';

    protected $fillable = [
        'airport_id',
        'operator_id',
        'season_code',
        'requested_time',
        'is_new_entrant',
        'status',
        'allocated_slot_id',
        'reviewer_id',
    ];

    protected $casts = [
        'is_new_entrant' => 'boolean',
        'requested_time' => 'integer',
        'status' => SlotRequestStatus::class,
    ];

    public function airport(): BelongsTo
    {
        return $this->belongsTo(AviationAirport::class, 'airport_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(AviationOperator::class, 'operator_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AviationSlotRequestTransition::class, 'slot_request_id');
    }

    public function transitionTo(SlotRequestStatus $newStatus, string $trigger, ?int $actorId = null, ?string $reason = null): void
    {
        SlotRequestStateMachine::assertCanTransition($this->status, $newStatus);

        $oldStatus = $this->status;
        $this->status = $newStatus;
        $this->save();

        $this->transitions()->create([
            'from_status' => $oldStatus->value,
            'to_status' => $newStatus->value,
            'trigger' => $trigger,
            'actor_id' => $actorId,
            'reason' => $reason,
        ]);
    }
}

