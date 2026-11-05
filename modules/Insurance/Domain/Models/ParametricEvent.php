<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class ParametricEvent extends Model
{
    use AppendOnly;

    protected $table = 'ins_param_events';

    /** @var list<string> */
    protected $fillable = [
        'event_key',
        'trigger_id',
        'observed_value',
        'oracle_payload',
        'oracle_signature',
        'policies_evaluated',
        'policies_paid',
        'total_payout_idr',
        'triggered_at',
        'ledger_transaction_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'observed_value' => 'integer',
            'oracle_payload' => 'array',
            'policies_evaluated' => 'integer',
            'policies_paid' => 'integer',
            'total_payout_idr' => 'integer',
            'triggered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ParametricTrigger, $this> */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(ParametricTrigger::class, 'trigger_id');
    }
}
