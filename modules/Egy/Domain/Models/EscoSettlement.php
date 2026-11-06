<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EscoSettlement extends Model
{
    protected $table = 'egy_esco_settlements';

    protected $fillable = [
        'esco_contract_id',
        'period_month',
        'baseline_kwh',
        'cdd_factor_bps',
        'adjusted_baseline_kwh',
        'actual_kwh',
        'gross_savings_kwh',
        'rate_idr_per_kwh',
        'total_savings_idr',
        'esco_share_bps',
        'esco_share_idr',
        'client_share_idr',
        'ledger_transaction_id',
        'status',
    ];

    protected $casts = [
        'baseline_kwh' => 'integer',
        'cdd_factor_bps' => 'integer',
        'adjusted_baseline_kwh' => 'integer',
        'actual_kwh' => 'integer',
        'gross_savings_kwh' => 'integer',
        'rate_idr_per_kwh' => 'integer',
        'total_savings_idr' => 'integer',
        'esco_share_bps' => 'integer',
        'esco_share_idr' => 'integer',
        'client_share_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EscoContract::class, 'esco_contract_id');
    }
}
