<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivingLine extends Model
{
    protected $table = 'prc_receiving_lines';

    protected $fillable = [
        'grn_id', 'po_line_id', 'description', 'ordered_qty', 'received_qty',
        'accepted_qty', 'rejected_qty', 'lot_number', 'expiry_date', 'warehouse_code', 'product_id',
    ];

    protected $casts = [
        'po_line_id' => 'integer', 'ordered_qty' => 'integer', 'received_qty' => 'integer',
        'accepted_qty' => 'integer', 'rejected_qty' => 'integer', 'expiry_date' => 'date',
        'product_id' => 'integer',
    ];

    public function grn(): BelongsTo
    {
        return $this->belongsTo(ReceivingReport::class, 'grn_id');
    }
}
