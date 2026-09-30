<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    protected $table = 'resto_goods_receipt_lines';

    protected $fillable = [
        'receipt_id',
        'po_line_id',
        'qty_received_base_unit',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'qty_received_base_unit' => 'string',
        'unit_cost' => 'string',
        'total_cost' => 'integer',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'receipt_id');
    }

    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }
}
