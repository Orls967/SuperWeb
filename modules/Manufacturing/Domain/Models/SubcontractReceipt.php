<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Subkontrak maklon: kirim bahan, terima olahan, biaya jasa → PR. */
class SubcontractReceipt extends Model
{
    use HasUuids;

    protected $table = 'mfg_subcontract_receipts';

    protected $fillable = [
        'production_order_id', 'supplier_id', 'shipment_ref', 'qty_in', 'qty_out',
        'service_cost_idr', 'pr_ref', 'status', 'created_by_user_id',
    ];

    protected $casts = [
        'qty_in' => 'decimal:6', 'qty_out' => 'decimal:6',
        'service_cost_idr' => 'integer', 'created_by_user_id' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
