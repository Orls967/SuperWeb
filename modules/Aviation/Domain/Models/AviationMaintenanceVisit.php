<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Aviation\Domain\Enums\MaintenanceVisitStatus;
use Modules\Aviation\Domain\StateMachines\MaintenanceVisitStateMachine;

class AviationMaintenanceVisit extends Model
{
    protected $table = 'avi_maintenance_visits';

    protected $fillable = [
        'aircraft_id',
        'visit_type',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'work_order_ref',
        'status',
        'deferral_expiry',
        'release_certificate_number',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'deferral_expiry' => 'date',
        'status' => 'string',
    ];

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(AviationAircraft::class, 'aircraft_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AviationMaintenanceTransition::class, 'maintenance_visit_id');
    }

    public function transitionTo(MaintenanceVisitStatus|string $newStatus, string $trigger, ?int $actorId = null, ?string $notes = null): void
    {
        $newStatusVal = is_string($newStatus) ? $newStatus : $newStatus->value;
        MaintenanceVisitStateMachine::assertCanTransition($this->status, $newStatusVal);

        $oldStatus = $this->status;
        $this->status = $newStatusVal;
        $this->save();

        $this->transitions()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatusVal,
            'trigger' => $trigger,
            'actor_id' => $actorId,
            'notes' => $notes,
        ]);
    }
}

