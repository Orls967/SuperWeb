<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProofOfDelivery extends LogisticsEntity
{
    protected $table = 'lgx_proofs_of_delivery';

    protected $fillable = [
        'shipment_id',
        'driver_id',
        'receiver_name',
        'otp_verified',
        'photo_path',
        'signature_path',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'otp_verified' => 'boolean',
            'delivered_at' => 'datetime',
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
