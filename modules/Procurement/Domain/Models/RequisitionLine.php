<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionLine extends Model
{
    protected $table = 'prc_requisition_lines';

    protected $fillable = [
        'requisition_id', 'description', 'supplier_item_id', 'qty', 'unit',
        'estimated_unit_price_idr', 'currency',
    ];

    protected $casts = [
        'supplier_item_id' => 'integer', 'qty' => 'integer',
        'estimated_unit_price_idr' => 'integer',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class, 'requisition_id');
    }
}
