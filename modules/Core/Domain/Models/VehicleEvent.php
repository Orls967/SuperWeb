<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Enums\VehicleEventType;

class VehicleEvent extends Model
{
    protected $table = 'core_vehicle_events';

    protected $fillable = [
        'vehicle_id',
        'sequence',
        'type',
        'payload',
        'occurred_at',
        'prev_hash',
        'hash',
        'actor_id',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'type' => VehicleEventType::class,
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Enforce append-only integrity: disallow updates and deletions
        static::updating(function () {
            throw new \RuntimeException('VehicleEvent is append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new \RuntimeException('VehicleEvent is append-only and cannot be deleted.');
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Canonicalize payload array recursively with sorted keys
     */
    public static function canonicalJson(array $data): string
    {
        $sortRecursive = function (&$item) use (&$sortRecursive) {
            if (is_array($item)) {
                // If associative array, sort by key
                if (array_keys($item) !== range(0, count($item) - 1)) {
                    ksort($item);
                }
                foreach ($item as &$val) {
                    if (is_array($val)) {
                        $sortRecursive($val);
                    }
                }
            }
        };

        $copy = $data;
        $sortRecursive($copy);

        return (string) json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Calculate SHA-256 hash for event
     */
    public static function calculateHash(
        string $prevHash,
        int $sequence,
        string|VehicleEventType $type,
        array $payload,
        CarbonInterface|\DateTimeInterface $occurredAt
    ): string {
        $typeVal = $type instanceof VehicleEventType ? $type->value : $type;
        $canonicalPayload = self::canonicalJson($payload);
        $occurredAtStr = $occurredAt->format('Y-m-d\TH:i:sP'); // ISO 8601

        $data = $prevHash.'|'.$sequence.'|'.$typeVal.'|'.$canonicalPayload.'|'.$occurredAtStr;

        return hash('sha256', $data);
    }
}
