<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Domain\Enums\AccountCurrentStatus;
use Modules\Party\Contracts\Party;

class ReinsuranceAccountCurrent extends Model
{
    protected $table = 'ins_rein_account_currents';

    /** @var list<string> */
    protected $fillable = [
        'number',
        'reinsurer_party_id',
        'period_ym',
        'premium_ceded_idr',
        'commission_idr',
        'claims_recoverable_idr',
        'reinstatement_idr',
        'net_due_idr',
        'status',
        'settlement_ledger_tx',
        'issued_at',
        'settled_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AccountCurrentStatus::class,
            'issued_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function reinsurer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'reinsurer_party_id');
    }
}
