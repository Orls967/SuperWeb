<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Domain\Concerns\AppendOnly;
use Modules\Insurance\Domain\Enums\CessionKind;

class CessionEvent extends Model
{
    use AppendOnly;

    protected $table = 'ins_cession_events';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'event_key',
        'kind',
        'policy_id',
        'claim_id',
        'treaty_id',
        'period_ym',
        'gross_idr',
        'retained_idr',
        'ceded_idr',
        'commission_idr',
        'reinstatement_idr',
        'snapshot',
        'snapshot_hash',
        'ledger_transaction_id',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => CessionKind::class,
            'snapshot' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return HasMany<CessionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CessionLine::class, 'event_id');
    }

    /** @return BelongsTo<InsurancePolicyPenuh, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicyPenuh::class, 'policy_id');
    }
}
