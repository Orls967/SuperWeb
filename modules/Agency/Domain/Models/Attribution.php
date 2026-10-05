<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Atribusi penjualan (last/first touch) + masa berlaku (45.4). */
class Attribution extends Model
{
    use HasUuids;

    protected $table = 'agy_attributions';

    protected $fillable = [
        'agent_id', 'source', 'reference_id', 'rule', 'touched_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'touched_at' => 'date', 'expires_at' => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function isExpired(?string $at = null): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->toDateString() < ($at ?? now()->toDateString());
    }
}
