<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturn extends Model
{
    use HasUuids;

    protected $table = 'prc_supplier_returns';

    protected $fillable = [
        'grn_id', 'number', 'qty', 'amount_idr', 'reason', 'status', 'ledger_transaction_id',
    ];

    protected $casts = ['qty' => 'integer', 'amount_idr' => 'integer', 'ledger_transaction_id' => 'integer'];

    public function grn(): BelongsTo
    {
        return $this->belongsTo(ReceivingReport::class, 'grn_id');
    }
}
