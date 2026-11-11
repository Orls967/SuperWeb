<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only immutable transition log for CoreAccessGrant (W8-D55).
 */
class CoreAccessGrantTransition extends Model
{
    public $timestamps = false;

    protected $table = 'core_access_grant_transitions';

    protected $fillable = [
        'grant_id',
        'from_status',
        'to_status',
        'trigger_action',
        'reason',
        'actor_id',
        'transitioned_at',
    ];

    protected function casts(): array
    {
        return [
            'grant_id' => 'integer',
            'actor_id' => 'integer',
            'transitioned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('TRANSITION_LOG_IMMUTABLE: Riwayat transisi akses bersifat imutabel append-only.');
        });

        static::deleting(function (): void {
            throw new DomainException('TRANSITION_LOG_IMMUTABLE: Riwayat transisi akses tidak boleh dihapus.');
        });
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(CoreAccessGrant::class, 'grant_id');
    }
}
