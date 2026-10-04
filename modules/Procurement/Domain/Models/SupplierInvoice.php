<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Supplier\Domain\Models\Supplier;

/**
 * Invoice pemasok + status 3-way match (PO–GRN–Invoice), 34.3.
 */
class SupplierInvoice extends Model
{
    use HasUuids;

    protected $table = 'prc_supplier_invoices';

    protected $fillable = [
        'number', 'supplier_id', 'po_id', 'status', 'invoice_amount_idr', 'matched_amount_idr',
        'variance_idr', 'price_tolerance_pct', 'qty_tolerance_pct', 'invoice_date', 'due_date',
        'currency', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'invoice_amount_idr' => 'integer', 'matched_amount_idr' => 'integer', 'variance_idr' => 'integer',
        'price_tolerance_pct' => 'integer', 'qty_tolerance_pct' => 'integer',
        'invoice_date' => 'date', 'due_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function po(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ThreeWayMatch::class, 'invoice_id');
    }
}
