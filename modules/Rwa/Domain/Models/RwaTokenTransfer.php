<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only fact record for RWA token transfers (PR5).
 * Rejects all update and delete attempts.
 */
class RwaTokenTransfer extends Model
{
    protected $table = 'rwa_token_transfers';

    public $timestamps = false;

    protected $fillable = [
        'rwa_asset_id',
        'from_user_id',
        'to_user_id',
        'amount_tokens',
        'transfer_type',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'created_at',
    ];

    protected $casts = [
        'rwa_asset_id' => 'integer',
        'from_user_id' => 'integer',
        'to_user_id' => 'integer',
        'amount_tokens' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('RwaTokenTransfer is an immutable append-only fact and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new DomainException('RwaTokenTransfer is an immutable append-only fact and cannot be deleted.');
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
