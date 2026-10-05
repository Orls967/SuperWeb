<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pembayaran piutang distributor. */
class ArPayment extends Model
{
    protected $table = 'dist_ar_payments';

    protected $fillable = ['invoice_id', 'amount_idr', 'method', 'reference', 'paid_at'];

    protected $casts = ['amount_idr' => 'integer', 'paid_at' => 'date'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ArInvoice::class, 'invoice_id');
    }
}
