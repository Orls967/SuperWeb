<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mining\Domain\Enums\WarehouseReceiptStatus;

class MiningMetalsWarehouseReceipt extends Model
{
    use HasUuids;

    protected $table = 'min_metals_warehouse_receipts';

    protected $fillable = [
        'id',
        'assay_id',
        'receipt_number',
        'warehouse_code',
        'warehouse_operator_name',
        'metal_commodity',
        'certified_weight_metric_tons',
        'bundle_or_ingot_count',
        'storage_location_rack',
        'estimated_market_value_idr',
        'haircut_percentage',
        'max_financing_limit_idr',
        'pledged_bank_name',
        'scf_facility_reference',
        'issued_at',
        'expires_at',
        'digital_seal_hash',
        'hmac_signature',
        'status',
        'metadata',
    ];

    protected $casts = [
        'certified_weight_metric_tons' => 'float',
        'bundle_or_ingot_count' => 'integer',
        'estimated_market_value_idr' => 'integer',
        'haircut_percentage' => 'float',
        'max_financing_limit_idr' => 'integer',
        'issued_at' => 'immutable_date',
        'expires_at' => 'immutable_date',
        'metadata' => 'array',
    ];

    public function assay(): BelongsTo
    {
        return $this->belongsTo(MiningSmelterQualityAssay::class, 'assay_id');
    }

    public function statusEnum(): WarehouseReceiptStatus
    {
        return WarehouseReceiptStatus::tryFrom((string) $this->status) ?? WarehouseReceiptStatus::ISSUED;
    }
}
