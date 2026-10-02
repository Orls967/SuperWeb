<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends LogisticsEntity
{
    protected $table = 'lgx_delivery_attempts';

    protected $fillable = [
        'shipment_id',
        'driver_id',
        'attempt_number',
        'outcome',
        'reason_code',
        'notes',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'attempted_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
