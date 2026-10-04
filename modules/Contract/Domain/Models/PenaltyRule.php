<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aturan denda keterlambatan (liquidated damages) — 29.2.
 *
 * `unit`: per_day_fixed (Rupiah/hari) atau percent_per_day (basis point/hari; 100bp = 1%).
 * Data peraturan bersifat simulasi.
 */
class PenaltyRule extends Model
{
    use HasUuids;

    protected $table = 'ctr_penalty_rules';

    public const UNIT_PER_DAY_FIXED = 'per_day_fixed';

    public const UNIT_PERCENT_PER_DAY = 'percent_per_day';

    protected $fillable = [
        'contract_id', 'name', 'unit', 'value',
        'cap_amount_idr', 'grace_days', 'is_active',
    ];

    protected $casts = [
        'value' => 'integer',
        'cap_amount_idr' => 'integer',
        'grace_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Hitung denda untuk keterlambatan tertentu.
     *
     * @param  int  $daysLate  Jumlah hari kalender lewat tanggal jatuh tempo (>= 0).
     * @param  int  $principalIdr  Pokok yang terlambat (untuk aturan %).
     */
    public function computePenalty(int $daysLate, int $principalIdr): int
    {
        if ($daysLate <= 0 || ! $this->is_active) {
            return 0;
        }

        $effectiveDays = max(0, $daysLate - (int) $this->grace_days);
        if ($effectiveDays === 0) {
            return 0;
        }

        $penalty = $this->unit === self::UNIT_PERCENT_PER_DAY
            ? (int) floor($principalIdr * (int) $this->value * $effectiveDays / 10_000)
            : (int) $this->value * $effectiveDays;

        if ($this->cap_amount_idr !== null) {
            $penalty = min($penalty, (int) $this->cap_amount_idr);
        }

        return max(0, $penalty);
    }
}
