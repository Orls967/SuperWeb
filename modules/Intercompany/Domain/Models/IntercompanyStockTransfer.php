<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntercompanyStockTransfer extends Model
{
    protected $table = 'ic_stock_transfers';

    protected $fillable = [
        'transfer_code',
        'source_entity',
        'destination_entity',
        'source_entity_id',
        'target_entity_id',
        'destination_entity_id',
        'item_sku',
        'quantity',
        'cost_price_idr',
        'transfer_price_idr',
        'transfer_date',
        'shipping_manifest_ref',
        'cif_value_idr',
        'import_duty_idr',
        'import_vat_idr',
        'status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'cif_value_idr' => 'integer',
        'import_duty_idr' => 'integer',
        'import_vat_idr' => 'integer',
    ];

    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'source_entity_id');
    }

    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'target_entity_id');
    }
}
