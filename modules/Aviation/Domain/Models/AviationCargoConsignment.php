<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Aviation\Domain\Enums\CargoConsignmentStatus;
use Modules\Aviation\Domain\StateMachines\CargoConsignmentStateMachine;

class AviationCargoConsignment extends Model
{
    protected $table = 'avi_cargo_consignments';

    protected $fillable = [
        'consignment_number',
        'flight_id',
        'shipper_party_id',
        'consignee_party_id',
        'weight_g',
        'volume_mm3',
        'nature_of_goods',
        'un_number',
        'dg_class',
        'is_dg',
        'dg_cleared',
        'required_temperature_min_c10',
        'required_temperature_max_c10',
        'uld_container_code',
        'status',
        'custody_hash_chain',
    ];

    protected $casts = [
        'weight_g' => 'integer',
        'volume_mm3' => 'integer',
        'is_dg' => 'boolean',
        'dg_cleared' => 'boolean',
        'required_temperature_min_c10' => 'integer',
        'required_temperature_max_c10' => 'integer',
        'status' => 'string',
    ];

    public function flight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'flight_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AviationCargoTransition::class, 'consignment_id');
    }

    public function transitionTo(CargoConsignmentStatus|string $newStatus, string $trigger, ?int $actorId = null, ?string $notes = null): void
    {
        $resolvedStatus = is_string($newStatus) ? $newStatus : $newStatus->value;
        CargoConsignmentStateMachine::assertCanTransition($this->status, $resolvedStatus);

        $initialStatus = $this->status;
        $this->status = $resolvedStatus;

        // Catat mutasi status konsinyasi kargo
        $this->save();

        $this->transitions()->create([
            'consignment_id' => $this->id,
            'from_status' => $initialStatus,
            'to_status' => $resolvedStatus,
            'trigger' => $trigger,
            'actor_id' => $actorId,
            'notes' => $notes,
        ]);
    }
}

