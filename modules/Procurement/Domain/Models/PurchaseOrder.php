<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Supplier\Domain\Models\Supplier;

class PurchaseOrder extends Model
{
    use HasUuids;

    protected $table = 'prc_purchase_orders';

    protected $fillable = [
        'number', 'supplier_id', 'requisition_id', 'rfq_id', 'tender_id', 'contract_id',
        'blanket_po_id', 'title', 'kind', 'status', 'version', 'currency', 'total_amount',
        'received_amount', 'expected_date', 'closed_at', 'notes', 'budget_center_id',
        'encumbrance_id', 'created_by_user_id',
    ];

    protected $casts = [
        'version' => 'integer', 'total_amount' => 'integer', 'received_amount' => 'integer',
        'expected_date' => 'date', 'closed_at' => 'datetime',
        'budget_center_id' => 'integer', 'encumbrance_id' => 'integer', 'created_by_user_id' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PoLine::class, 'po_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PoVersion::class, 'po_id')->orderBy('version');
    }

    public function importProfile(): HasMany
    {
        return $this->hasMany(ImportProfile::class, 'po_id');
    }

    public function budgetCenter(): BelongsTo
    {
        return $this->belongsTo(BudgetCenter::class, 'budget_center_id');
    }

    public function receivingReports(): HasMany
    {
        return $this->hasMany(ReceivingReport::class, 'po_id')->orderByDesc('received_at');
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class, 'po_id');
    }

    public function landedCosts(): HasMany
    {
        return $this->hasMany(LandedCost::class, 'po_id');
    }
}
