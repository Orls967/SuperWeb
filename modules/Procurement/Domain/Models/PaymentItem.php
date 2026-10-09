<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentItem extends Model
{
    protected $table = 'prc_payment_items';

    protected $fillable = [
        'batch_id', 'invoice_id', 'amount_idr', 'early_discount_idr', 'status', 'ledger_transaction_id',
    ];

    protected $casts = [
        'invoice_id' => 'string', 'amount_idr' => 'integer', 'early_discount_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'batch_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'invoice_id');
    }
}
