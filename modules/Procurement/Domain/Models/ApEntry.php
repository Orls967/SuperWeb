<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplier\Domain\Models\Supplier;

/**
 * Entri subledger AP (34.4): GR/IR, AP, PPN masukan, PPh 23, PPV, uang muka, kredit memo.
 */
class ApEntry extends Model
{
    protected $table = 'prc_ap_entries';

    protected $fillable = [
        'invoice_id', 'supplier_id', 'kind', 'amount_idr', 'direction',
        'ledger_transaction_id', 'reference',
    ];

    protected $casts = [
        'invoice_id' => 'string', 'amount_idr' => 'integer', 'ledger_transaction_id' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
