<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Asset\Domain\Enums\AssetEventType;

/**
 * Riwayat aset hash-chain append-only (30.4), meniru pola Vehicle Passport.
 *
 * Baris tidak boleh diubah/dihapus: pelanggaran diblokir di `booted()`.
 */
class AssetEvent extends Model
{
    use HasUuids;

    protected $table = 'ast_events';

    protected $fillable = [
        'asset_id', 'sequence', 'event_type', 'payload',
        'prev_hash', 'hash', 'created_by_name', 'occurred_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'event_type' => AssetEventType::class,
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('AssetEvent is append-only and cannot be modified.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('AssetEvent is append-only and cannot be deleted.');
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * Digest SHA-256 deterministik untuk rantai hash aset.
     */
    public static function calculateHash(
        string $prevHash,
        int $sequence,
        string $eventType,
        string $payloadJson,
        string $occurredIso
    ): string {
        $canonical = $prevHash.'|'.$sequence.'|'.$eventType.'|'.hash('sha256', $payloadJson).'|'.$occurredIso;

        return hash('sha256', $canonical);
    }
}
