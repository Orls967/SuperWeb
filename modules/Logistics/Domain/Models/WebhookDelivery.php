<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

class WebhookDelivery extends LogisticsEntity
{
    protected $table = 'lgx_webhook_deliveries';

    protected $fillable = [
        'endpoint_id',
        'event_type',
        'payload',
        'idempotency_key',
        'attempt',
        'response_code',
        'response_body',
        'status',
        'next_retry_at',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt' => 'integer',
        'response_code' => 'integer',
        'next_retry_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public const MAX_RETRIES = 8;

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    /**
     * Calculate next retry delay using exponential backoff.
     * Retry delays: 30s, 60s, 120s, 240s, 480s, 960s, 1920s, 3840s
     */
    public function calculateNextRetryDelay(): int
    {
        return (int) (30 * pow(2, $this->attempt - 1));
    }

    public function isDeadLetter(): bool
    {
        return $this->attempt >= self::MAX_RETRIES;
    }
}
