<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboxMessage extends Model
{
    public const MAX_RETRIES = 5;

    protected $table = 'core_outbox';

    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'headers',
        'idempotency_key',
        'status',
        'attempts',
        'last_error',
        'next_retry_at',
        'dispatched_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    public function dispatches(): HasMany
    {
        return $this->hasMany(OutboxDispatch::class, 'outbox_id');
    }

    public function isDeadLetter(): bool
    {
        return $this->attempts >= self::MAX_RETRIES;
    }

    public function calculateNextRetryDelay(): int
    {
        return (int) (15 * pow(2, max(0, $this->attempts - 1)));
    }
}
