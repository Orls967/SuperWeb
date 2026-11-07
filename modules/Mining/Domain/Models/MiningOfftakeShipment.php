<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mining\Domain\Enums\OfftakeShipmentStatus;

class MiningOfftakeShipment extends Model
{
    use HasUuids;

    protected $table = 'min_offtake_shipments';

    protected $fillable = [
        'shipment_number',
        'contract_id',
        'party_ref',
        'vessel_name',
        'laycan_start',
        'laycan_end',
        'nominated_volume_tons',
        'loaded_volume_tons',
        'lc_ref',
        'status',
        'provisional_invoice_ref',
        'final_invoice_ref',
        'is_export',
        'export_prohibited_diverted',
        'diversion_reason',
    ];

    protected $casts = [
        'status' => OfftakeShipmentStatus::class,
        'party_ref' => 'integer',
        'nominated_volume_tons' => 'integer',
        'loaded_volume_tons' => 'integer',
        'provisional_invoice_ref' => 'integer',
        'final_invoice_ref' => 'integer',
        'is_export' => 'boolean',
        'export_prohibited_diverted' => 'boolean',
        'laycan_start' => 'date',
        'laycan_end' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MiningOfftakeContract::class, 'contract_id');
    }

    public function isFullySettled(): bool
    {
        return $this->status === OfftakeShipmentStatus::SETTLED;
    }
}
