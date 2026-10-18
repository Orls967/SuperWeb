<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreMobileEpodRecord extends Model
{
    protected $table = 'core_mobile_epod_records';

    protected $fillable = [
        'epod_code',
        'shipment_ref',
        'driver_ref',
        'recipient_name',
        'recipient_role',
        'photo_proof_hash',
        'signature_hash',
        'latitude',
        'longitude',
        'delivered_offline_at',
        'synced_at',
        'signature_payload_b64',
        'is_tampered',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'delivered_offline_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_tampered' => 'boolean',
        ];
    }
}
