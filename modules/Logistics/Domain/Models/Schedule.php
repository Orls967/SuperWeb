<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\TransportMode;

class Schedule extends LogisticsEntity
{
    protected $table = 'lgx_schedules';

    protected $fillable = [
        'schedule_number',
        'mode',
        'asset_type',
        'asset_id',
        'driver_id',
        'voyage_number',
        'sequence',
        'origin_location_id',
        'destination_location_id',
        'etd',
        'eta',
        'cutoff_at',
        'atd',
        'ata',
        'status',
        'cap_weight_kg',
        'cap_volume_dm3',
        'cap_teu',
        'cap_uld_positions',
        'used_weight_kg',
        'used_volume_dm3',
        'used_teu',
        'used_uld_positions',
    ];

    protected $casts = [
        'mode' => TransportMode::class,
        'status' => ScheduleStatus::class,
        'sequence' => 'integer',
        'etd' => 'datetime',
        'eta' => 'datetime',
        'cutoff_at' => 'datetime',
        'atd' => 'datetime',
        'ata' => 'datetime',
        'cap_weight_kg' => 'decimal:3',
        'used_weight_kg' => 'decimal:3',
        'cap_volume_dm3' => 'integer',
        'used_volume_dm3' => 'integer',
        'cap_teu' => 'integer',
        'used_teu' => 'integer',
        'cap_uld_positions' => 'integer',
        'used_uld_positions' => 'integer',
    ];

    public function asset(): MorphTo
    {
        return $this->morphTo();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function isPastCutoff(): bool
    {
        return now()->greaterThan($this->cutoff_at);
    }

    public function remainingWeightKg(): BigDecimal
    {
        $cap = ! empty($this->cap_weight_kg) ? (string) $this->cap_weight_kg : '0.000';
        $used = ! empty($this->used_weight_kg) ? (string) $this->used_weight_kg : '0.000';

        return BigDecimal::of($cap)->minus(BigDecimal::of($used));
    }

    public function remainingVolumeDm3(): int
    {
        return max(0, ((int) $this->cap_volume_dm3) - ((int) $this->used_volume_dm3));
    }

    public function remainingTeu(): int
    {
        return max(0, ((int) $this->cap_teu) - ((int) $this->used_teu));
    }

    public function remainingUldPositions(): int
    {
        return max(0, ((int) $this->cap_uld_positions) - ((int) $this->used_uld_positions));
    }

    /**
     * Verify whether the schedule has sufficient capacity across all dimensions.
     */
    public function hasAvailableCapacity(
        string|int|float|BigDecimal $weightKg,
        int $volumeDm3 = 0,
        int $teu = 0,
        int $uldPositions = 0
    ): bool {
        $neededWeight = $weightKg instanceof BigDecimal ? $weightKg : BigDecimal::of((string) ($weightKg ?: '0'));
        if ($this->remainingWeightKg()->isLessThan($neededWeight)) {
            return false;
        }

        if ($volumeDm3 > 0 && $this->remainingVolumeDm3() < $volumeDm3) {
            return false;
        }

        if ($teu > 0 && $this->remainingTeu() < $teu) {
            return false;
        }

        if ($uldPositions > 0 && $this->remainingUldPositions() < $uldPositions) {
            return false;
        }

        return true;
    }

    /**
     * Check whether this schedule time range overlaps with another given window.
     */
    public function overlapsWith(CarbonInterface $start, CarbonInterface $end): bool
    {
        return $this->etd->lessThan($end) && $this->eta->greaterThan($start);
    }
}
