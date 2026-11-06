<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EgyStatusTransition extends Model
{
    use HasUuids;

    protected $table = 'egy_status_transitions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'entity_type',
        'entity_id',
        'from_status',
        'to_status',
        'reason',
        'metadata',
        'triggered_by',
        'transitioned_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'transitioned_at' => 'datetime',
    ];

    public static function record(
        string $entityType,
        string $entityId,
        string $fromStatus,
        string $toStatus,
        ?string $reason = null,
        ?array $metadata = null,
        ?string $triggeredBy = null
    ): self {
        return self::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => $metadata,
            'triggered_by' => $triggeredBy,
            'transitioned_at' => now(),
        ]);
    }
}
