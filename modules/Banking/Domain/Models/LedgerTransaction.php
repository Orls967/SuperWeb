<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Banking\Domain\Exceptions\ImmutableRecordException;
use Modules\Shared\Domain\Traits\HasUuid;

class LedgerTransaction extends Model
{
    use HasUuid;

    protected $table = 'bank_ledger_transactions';

    protected $fillable = [
        'uuid',
        'type',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'payload_hash',
        'description',
        'meta',
        'posted_at',
        'created_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            throw new ImmutableRecordException(self::class, 'update');
        });

        static::deleting(function (self $model): void {
            throw new ImmutableRecordException(self::class, 'delete');
        });
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
