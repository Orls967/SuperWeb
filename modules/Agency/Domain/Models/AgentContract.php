<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kontrak keagenan: wilayah, eksklusivitas, non-compete (45.2). */
class AgentContract extends Model
{
    use HasUuids;

    protected $table = 'agy_contracts';

    protected $fillable = [
        'agent_id', 'contract_ref', 'territory_scope', 'product_scope', 'exclusive',
        'non_compete', 'valid_from', 'valid_until', 'status', 'termination_reason',
    ];

    protected $casts = [
        'product_scope' => 'array', 'exclusive' => 'boolean', 'non_compete' => 'boolean',
        'valid_from' => 'date', 'valid_until' => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function isActiveAt(?string $at = null): bool
    {
        $date = $at ?? now()->toDateString();

        return $this->status === 'active'
            && $this->valid_from->toDateString() <= $date
            && ($this->valid_until === null || $this->valid_until->toDateString() >= $date);
    }
}
