<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningFuelAnomalyHistory extends Model
{
    use HasUuids;

    protected $table = 'min_fuel_anomaly_histories';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'case_id',
        'from_status',
        'to_status',
        'actor_ref',
        'actor_id',
        'notes',
    ];

    public function getActorIdAttribute(): ?string
    {
        return $this->attributes['actor_ref'] ?? null;
    }

    public function setActorIdAttribute(?string $value): void
    {
        $this->attributes['actor_ref'] = $value;
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(MiningFuelAnomalyCase::class, 'case_id');
    }
}
