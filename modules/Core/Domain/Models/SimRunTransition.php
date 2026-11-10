<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class SimRunTransition extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sim_run_transitions';

    protected $fillable = [
        'run_id',
        'from_status',
        'to_status',
        'reason',
        'actor_id',
        'actor_identifier',
        'actor_role',
        'metadata',
        'created_at',
    ];

    public function getActorIdAttribute(): ?string
    {
        return $this->attributes['actor_identifier'] ?? $this->attributes['actor_id'] ?? null;
    }

    public function setActorIdAttribute(?string $value): void
    {
        $this->attributes['actor_identifier'] = $value;
    }

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Invarian append-only PR5: Riwayat transisi dilarang diubah atau dihapus.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('APPEND_ONLY_VIOLATION: Entri riwayat transisi simulasi bersifat append-only dan tidak boleh diubah.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('APPEND_ONLY_VIOLATION: Entri riwayat transisi simulasi bersifat append-only dan tidak boleh dihapus.');
        });
    }

    /**
     * @return BelongsTo<SimRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(SimRun::class, 'run_id');
    }
}
