<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Party\Domain\Enums\RiskTier;

class CreditProfile extends Model
{
    protected $table = 'pty_credit_profiles';

    protected $fillable = [
        'party_id', 'credit_limit_idr', 'current_exposure_idr',
        'internal_score', 'risk_tier', 'exposure_breakdown', 'last_scored_at',
    ];

    protected $casts = [
        'risk_tier' => RiskTier::class,
        'exposure_breakdown' => 'array',
        'last_scored_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function availableCredit(): int
    {
        return max(0, $this->credit_limit_idr - $this->current_exposure_idr);
    }

    public function utilizationPercent(): float
    {
        if ($this->credit_limit_idr <= 0) {
            return 0.0;
        }

        return round(($this->current_exposure_idr / $this->credit_limit_idr) * 100, 2);
    }
}
