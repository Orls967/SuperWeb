<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookSubscription extends Model
{
    protected $table = 'intg_webhook_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'subscription_id');
    }
}
