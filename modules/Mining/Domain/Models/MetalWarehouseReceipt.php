<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MetalWarehouseReceipt extends Model
{
    protected $table = 'min_metal_warehouse_receipts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'receipt_number',
        'warehouse_id',
        'commodity',
        'stored_tonnage',
        'receipt_hash',
        'is_collateralized',
        'collateral_facility_id',
        'financing_amount_minor',
        'status',
    ];

    protected $casts = [
        'stored_tonnage' => 'float',
        'is_collateralized' => 'boolean',
        'financing_amount_minor' => 'integer',
    ];
}
