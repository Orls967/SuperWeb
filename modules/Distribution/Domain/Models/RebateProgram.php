<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Program rebate: volume / pertumbuhan / bertingkat (43.6). */
class RebateProgram extends Model
{
    protected $table = 'dist_rebate_programs';

    protected $fillable = [
        'code', 'name', 'kind', 'valid_from', 'valid_until',
        'rate_percent', 'threshold_qty', 'tier_breaks', 'is_active',
    ];

    protected $casts = [
        'valid_from' => 'date', 'valid_until' => 'date',
        'rate_percent' => 'decimal:4', 'threshold_qty' => 'decimal:6',
        'tier_breaks' => 'array', 'is_active' => 'boolean',
    ];

    public function accruals(): HasMany
    {
        return $this->hasMany(RebateAccrual::class, 'program_id');
    }

    public function isLive(?string $at = null): bool
    {
        $date = $at ?? now()->toDateString();

        return $this->is_active
            && $this->valid_from->toDateString() <= $date
            && $this->valid_until->toDateString() >= $date;
    }

    /** Hitung rate rebate untuk qty (tiered memakai tier_breaks; lainnya rate_percent). */
    public function rateFor(float $qty): float
    {
        if ($this->kind === 'volume' || $this->kind === 'growth') {
            if ($this->kind === 'growth' && $qty < (float) $this->threshold_qty) {
                return 0.0;
            }

            return (float) $this->rate_percent;
        }

        // tiered: [{min_qty, rate_percent}] — pilih tingkat tertinggi yang terpenuhi.
        $rate = 0.0;
        foreach ($this->tier_breaks ?? [] as $break) {
            if ($qty >= (float) ($break['min_qty'] ?? 0)) {
                $rate = (float) ($break['rate_percent'] ?? 0);
            }
        }

        return $rate;
    }
}
