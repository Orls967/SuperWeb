<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan penerimaan GRN & evaluasi toleransi penerimaan (X41-1).
 */
class ReceivingReceipt extends Model
{
    protected $table = 'wms_receiving_receipts';

    protected $fillable = [
        'receipt_number',
        'warehouse_id',
        'product_id',
        'expected_qty_base',
        'received_qty_base',
        'variance_bps',
        'tolerance_decision',
        'lot_number',
        'expiry_date',
        'status',
        'received_by_user_id',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
        'expected_qty_base' => 'integer',
        'received_qty_base' => 'integer',
        'variance_bps' => 'integer',
        'expiry_date' => 'date',
        'received_by_user_id' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
