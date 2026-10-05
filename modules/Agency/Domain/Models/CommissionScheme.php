<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Skema komisi: flat / percent / slab / target_bonus + override upline. */
class CommissionScheme extends Model
{
    protected $table = 'agy_commission_schemes';

    protected $fillable = [
        'agent_id', 'code', 'name', 'basis', 'flat_amount_idr', 'rate_percent',
        'scope', 'scope_ref', 'slabs', 'target_amount_idr', 'bonus_amount_idr',
        'level', 'override_rate_percent', 'valid_from', 'valid_until', 'is_active',
    ];

    protected $casts = [
        'flat_amount_idr' => 'integer', 'rate_percent' => 'decimal:4',
        'slabs' => 'array', 'target_amount_idr' => 'integer', 'bonus_amount_idr' => 'integer',
        'level' => 'integer', 'override_rate_percent' => 'decimal:4',
        'valid_from' => 'date', 'valid_until' => 'date', 'is_active' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function isLive(?string $at = null): bool
    {
        $date = $at ?? now()->toDateString();

        return $this->is_active
            && $this->valid_from->toDateString() <= $date
            && ($this->valid_until === null || $this->valid_until->toDateString() >= $date);
    }

    /** Hitung komisi kotor (tanpa override upline). */
    public function calculate(int $baseAmountIdr, float $qty = 1): int
    {
        return match ($this->basis) {
            'flat' => (int) $this->flat_amount_idr,
            'percent' => (int) floor($baseAmountIdr * (float) $this->rate_percent / 100),
            'slab' => (int) floor($baseAmountIdr * $this->slabRate($baseAmountIdr) / 100),
            'target_bonus' => $baseAmountIdr >= (int) $this->target_amount_idr
                ? (int) $this->bonus_amount_idr
                : 0,
            default => 0,
        };
    }

    /** Rate slab tertinggi yang terpenuhi. */
    public function slabRate(int $baseAmountIdr): float
    {
        $rate = 0.0;
        foreach ($this->slabs ?? [] as $slab) {
            if ($baseAmountIdr >= (int) ($slab['min_amount_idr'] ?? 0)) {
                $rate = (float) ($slab['rate_percent'] ?? 0);
            }
        }

        return $rate;
    }
}
