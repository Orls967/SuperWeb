<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCertification extends Model
{
    use HasUuids;

    protected $table = 'agy_certifications';

    protected $fillable = [
        'agent_id', 'type', 'license_number', 'issuing_body', 'issued_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function isValidAt(?string $at = null): bool
    {
        $date = $at ?? now()->toDateString();

        return $this->status === 'verified'
            && ($this->expires_at === null || $this->expires_at->toDateString() >= $date);
    }
}
