<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'core_audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'correlation_id',
        'impact_type',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'context',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('AuditLog is append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new \RuntimeException('AuditLog is append-only and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Catat audit log baru dengan data konteks lengkap.
     */
    public static function record(
        string $action,
        ?Model $auditable = null,
        array $context = [],
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        ?string $correlationId = null,
        ?string $impactType = null
    ): self {
        $currentUser = $user ?? (auth()->check() ? auth()->user() : null);

        $ipAddress = null;
        $userAgent = null;
        try {
            $ipAddress = request()?->ip();
            $userAgent = request()?->userAgent();
        } catch (\Throwable) {
            // CLI or background context without active HTTP request
        }

        return self::create([
            'user_id' => $currentUser?->id,
            'action' => $action,
            'correlation_id' => $correlationId ?? (request()?->header('X-Correlation-ID') ?: null),
            'impact_type' => $impactType,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => $context,
            'created_at' => now(),
        ]);
    }
}
