<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends LogisticsEntity
{
    protected $table = 'lgx_tracking_events';

    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'sequence',
        'event_type',
        'location_id',
        'actor_id',
        'actor_role',
        'description',
        'payload',
        'occurred_at',
        'prev_hash',
        'hash',
        'created_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Enforce append-only integrity: disallow updates and deletions
        static::updating(function () {
            throw new \RuntimeException('TrackingEvent is append-only and immutable. Updates are strictly forbidden.');
        });

        static::deleting(function () {
            throw new \RuntimeException('TrackingEvent is append-only and immutable. Deletions are strictly forbidden.');
        });
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Compute genesis prev_hash for the first event of a shipment.
     */
    public static function genesisHash(int $shipmentId): string
    {
        return hash('sha256', "GENESIS:SHIPMENT:{$shipmentId}");
    }

    /**
     * Canonicalize payload array recursively with deterministic sorted keys.
     */
    public static function canonicalJson(array $data): string
    {
        $sortRecursive = function (&$item) use (&$sortRecursive) {
            if (is_array($item)) {
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
     * Calculate SHA-256 canonical hash for chain of custody verification.
     */
    public static function calculateHash(
        string $prevHash,
        int $sequence,
        string $eventType,
        array $payload,
        CarbonInterface|DateTimeInterface $occurredAt
    ): string {
        $canonicalPayload = self::canonicalJson($payload);
        $occurredAtStr = $occurredAt->format('Y-m-d\TH:i:sP'); // ISO 8601

        $data = $prevHash.'|'.$sequence.'|'.$eventType.'|'.$canonicalPayload.'|'.$occurredAtStr;

        return hash('sha256', $data);
    }
}
