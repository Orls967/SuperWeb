<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $table = 'intg_webhook_deliveries';

    protected $fillable = [
        'subscription_id',
        'event_id',
        'payload',
        'signature',
        'http_status',
        'delivery_status',
        'attempt_count',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }
}
