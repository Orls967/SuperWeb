<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferPricingTrueUp extends Model
{
    protected $table = 'ic_tp_true_ups';

    protected $fillable = [
        'adjustment_code',
        'tp_rule_id',
        'rule_id',
        'entity_id',
        'affected_entity',
        'fiscal_year',
        'actual_margin_bps',
        'target_median_bps',
        'target_margin_bps',
        'adjustment_amount_idr',
        'status',
        'notes',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'actual_margin_bps' => 'integer',
        'target_median_bps' => 'integer',
        'adjustment_amount_idr' => 'integer',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'entity_id');
    }
}
