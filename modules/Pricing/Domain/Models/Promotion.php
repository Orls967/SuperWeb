<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Promo dagang dengan anggaran & klaim (44.3). */
class Promotion extends Model
{
    use HasUuids;

    protected $table = 'pric_promotions';

    protected $fillable = [
        'code', 'name', 'mechanic', 'budget_idr', 'spent_idr', 'percent_off',
        'amount_off_idr', 'applies_to', 'valid_from', 'valid_until', 'is_active', 'notes',
    ];

    protected $casts = [
        'budget_idr' => 'integer', 'spent_idr' => 'integer', 'percent_off' => 'decimal:4',
        'amount_off_idr' => 'integer', 'applies_to' => 'array',
        'valid_from' => 'date', 'valid_until' => 'date', 'is_active' => 'boolean',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(PromotionClaim::class, 'promotion_id');
    }

    public function isLive(?string $at = null): bool
    {
        $date = $at ?? now()->toDateString();

        return $this->is_active
            && $this->valid_from->toDateString() <= $date
            && $this->valid_until->toDateString() >= $date;
    }

    public function remainingBudget(): int
    {
        return max(0, (int) $this->budget_idr - (int) $this->spent_idr);
    }
}
