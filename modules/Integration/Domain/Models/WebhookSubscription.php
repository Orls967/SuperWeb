<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookSubscription extends Model
{
    protected $table = 'intg_webhook_subscriptions';

    protected $fillable = [
        'partner_id',
        'event_type',
        'target_url',
        'secret_key',
        'is_active',
    ];

    protected $casts = [
        'secret_key' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'subscription_id');
    }
}
