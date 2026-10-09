<?php

declare(strict_types=1);

namespace Modules\Partner\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueShare extends Model
{
    use HasUuids;

    protected $table = 'ptn_revenue_shares';

    protected $fillable = [
        'partner_id', 'period', 'gross_revenue_idr', 'deductible_cost_idr',
        'net_base_idr', 'share_rate_percent', 'share_amount_idr', 'status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'gross_revenue_idr' => 'integer',
        'deductible_cost_idr' => 'integer',
        'net_base_idr' => 'integer',
        'share_rate_percent' => 'float',
        'share_amount_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
