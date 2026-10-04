<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplier\Domain\Models\Supplier;

class CreditMemo extends Model
{
    use HasUuids;

    protected $table = 'prc_credit_memos';

    protected $fillable = ['supplier_id', 'invoice_id', 'number', 'amount_idr', 'reason', 'status', 'ledger_transaction_id'];

    protected $casts = ['amount_idr' => 'integer', 'ledger_transaction_id' => 'integer'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'invoice_id');
    }
}
