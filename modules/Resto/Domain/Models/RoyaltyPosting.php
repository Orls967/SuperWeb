<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Shared\Domain\Traits\HasUuid;

class RoyaltyPosting extends Model
{
    use HasUuid;

    protected $table = 'resto_royalty_postings';

    protected $fillable = [
        'uuid',
        'contract_id',
        'outlet_id',
        'date',
        'gross_sales',
        'net_sales',
        'royalty_amount',
        'marketing_fee_amount',
        'franchisee_net_share',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_sales' => 'integer',
        'net_sales' => 'integer',
        'royalty_amount' => 'integer',
        'marketing_fee_amount' => 'integer',
        'franchisee_net_share' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(OutletContract::class, 'contract_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }
}
