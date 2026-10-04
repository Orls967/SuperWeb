<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ContractVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'ctr_contract_versions';

    protected $fillable = [
        'contract_id', 'sequence', 'change_type', 'body',
        'metadata', 'prev_hash', 'hash', 'created_by_name', 'created_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('ContractVersion is append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new RuntimeException('ContractVersion is append-only and cannot be deleted.');
        });
    }

    /**
     * Compute deterministic hash-chain SHA-256 digest.
     */
    public static function calculateHash(
        string $prevHash,
        int $sequence,
        string $changeType,
        string $body,
        string $createdAtIso
    ): string {
        $canonical = $prevHash.'|'.$sequence.'|'.$changeType.'|'.hash('sha256', $body).'|'.$createdAtIso;

        return hash('sha256', $canonical);
    }
}
