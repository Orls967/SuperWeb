<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

class WebhookEndpoint extends LogisticsEntity
{
    protected $table = 'lgx_webhook_endpoints';

    protected $fillable = [
        'url',
        'secret',
        'events',
        'is_active',
        'shipper_id',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['secret'];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }
}
