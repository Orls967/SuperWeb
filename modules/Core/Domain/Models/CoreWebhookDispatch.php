<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreWebhookDispatch extends Model
{
    protected $table = 'core_webhook_dispatches';

    protected $fillable = [
        'tenant_id',
        'webhook_id',
        'event_delivery_id',
        'outbound_event_ref',
        'event_type',
        'timestamp_unix',
        'payload_json',
        'signature_header',
        'http_status',
        'response_excerpt',
        'status',
        'attempt_count',
        'idempotency_key',
    ];

    protected $casts = [
        'timestamp_unix' => 'integer',
        'http_status' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(CoreWebhook::class, 'webhook_id');
    }
}
