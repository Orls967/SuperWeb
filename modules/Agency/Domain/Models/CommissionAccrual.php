<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Akrual komisi: hold sampai retur lewat, clawback negatif (45.5/45.6). */
class CommissionAccrual extends Model
{
    protected $table = 'agy_commission_accruals';

    protected $fillable = [
        'agent_id', 'reference_id', 'source_type', 'accrued_at', 'base_amount_idr',
        'rate_percent', 'amount_idr', 'status', 'hold_until', 'note',
    ];

    protected $casts = [
        'accrued_at' => 'date', 'base_amount_idr' => 'integer', 'rate_percent' => 'decimal:4',
        'amount_idr' => 'integer', 'hold_until' => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function isHoldExpired(?string $at = null): bool
    {
        return $this->status === 'hold'
            && $this->hold_until->toDateString() <= ($at ?? now()->toDateString());
    }
}
