<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Varians vs standar: positif = unfavourable, negatif = favourable. */
class Variance extends Model
{
    protected $table = 'mfg_variances';

    protected $fillable = [
        'order_id', 'kind', 'amount_idr', 'policy', 'posted', 'ledger_transaction_id', 'notes',
    ];

    protected $casts = [
        'amount_idr' => 'integer', 'posted' => 'boolean', 'ledger_transaction_id' => 'integer',
    ];

    public const KINDS = ['price', 'usage', 'labor_efficiency', 'overhead_volume', 'yield'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'order_id');
    }
}
