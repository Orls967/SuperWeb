<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreeWayMatch extends Model
{
    protected $table = 'prc_three_way_matches';

    protected $fillable = [
        'invoice_id', 'grn_id', 'po_id', 'result', 'po_amount_idr', 'grn_amount_idr',
        'invoice_amount_idr', 'price_variance_idr', 'qty_variance_pct', 'notes',
    ];

    protected $casts = [
        'po_amount_idr' => 'integer', 'grn_amount_idr' => 'integer', 'invoice_amount_idr' => 'integer',
        'price_variance_idr' => 'integer', 'qty_variance_pct' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'invoice_id');
    }
}
