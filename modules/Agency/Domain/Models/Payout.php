<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Payout komisi periodik + PPh 21/23 simulasi (45.7). */
class Payout extends Model
{
    protected $table = 'agy_payouts';

    protected $fillable = [
        'agent_id', 'number', 'period', 'gross_idr', 'withheld_tax_idr', 'net_idr',
        'status', 'approval_id', 'ledger_transaction_id', 'proof_reference', 'paid_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'gross_idr' => 'integer', 'withheld_tax_idr' => 'integer', 'net_idr' => 'integer',
        'approval_id' => 'integer', 'ledger_transaction_id' => 'integer',
        'paid_at' => 'datetime', 'created_by_user_id' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class, 'payout_id');
    }
}
