<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoLine extends Model
{
    protected $table = 'prc_po_lines';

    protected $fillable = ['po_id', 'description', 'qty', 'unit', 'unit_price', 'line_total', 'received_qty'];

    protected $casts = ['qty' => 'integer', 'unit_price' => 'integer', 'line_total' => 'integer', 'received_qty' => 'integer'];

    public function po(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
}
