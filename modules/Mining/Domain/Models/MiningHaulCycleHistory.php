<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningHaulCycleHistory extends Model
{
    use HasUuids;

    protected $table = 'min_haul_cycle_histories';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'haul_cycle_id',
        'from_status',
        'to_status',
        'actor_ref',
        'actor_id',
        'notes',
        'recorded_at',
    ];

    public function getActorIdAttribute(): ?string
    {
        return $this->attributes['actor_ref'] ?? null;
    }

    public function setActorIdAttribute(?string $value): void
    {
        $this->attributes['actor_ref'] = $value;
    }

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MiningHaulCycle::class, 'haul_cycle_id');
    }
}
