<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\ActiveLoadConflictException;
use Modules\Logistics\Domain\Exceptions\InvalidLoadConsolidationException;
use Modules\Logistics\Domain\Exceptions\MissingSolasVgmException;
use Modules\Logistics\Domain\Services\DgSegregationValidator;

class Load extends LogisticsEntity
{
    protected $table = 'lgx_loads';

    protected $fillable = [
        'load_number',
        'load_type',
        'loadable_type',
        'loadable_id',
        'schedule_id',
        'service_type',
        'origin_location_id',
        'destination_location_id',
        'status',
        'max_weight_kg',
        'max_volume_dm3',
        'current_weight_kg',
        'current_volume_dm3',
        'is_reefer',
        'target_temp_c10',
        'seal_number',
        'sealed_at',
        'vgm_kg',
        'vgm_method',
        'vgm_certified_by',
        'vgm_verified_at',
    ];

    protected $casts = [
        'max_weight_kg' => 'decimal:3',
        'current_weight_kg' => 'decimal:3',
        'max_volume_dm3' => 'integer',
        'current_volume_dm3' => 'integer',
        'is_reefer' => 'boolean',
        'target_temp_c10' => 'integer',
        'sealed_at' => 'datetime',
        'vgm_kg' => 'decimal:3',
        'vgm_verified_at' => 'datetime',
    ];

    public function loadable(): MorphTo
    {
        return $this->morphTo();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoadItem::class, 'load_id')->orderBy('sequence');
    }

    public function isFcl(): bool
    {
        return strtolower($this->service_type) === 'fcl';
    }

    public function isLcl(): bool
    {
        return strtolower($this->service_type) === 'lcl';
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            'planning',
            'consolidating',
            'sealed',
            'loaded',
            'in_transit',
        ], true);
    }

    public function hasVgm(): bool
    {
        return $this->vgm_kg !== null && $this->vgm_verified_at !== null;
    }

    public function remainingWeightKg(): BigDecimal
    {
        $max = ! empty($this->max_weight_kg) ? (string) $this->max_weight_kg : '0.000';
        $curr = ! empty($this->current_weight_kg) ? (string) $this->current_weight_kg : '0.000';

        return BigDecimal::of($max)->minus(BigDecimal::of($curr));
    }

    public function remainingVolumeDm3(): int
    {
        return max(0, ((int) $this->max_volume_dm3) - ((int) $this->current_volume_dm3));
    }

    public function canFit(float|string|BigDecimal $weightKg, int $volumeDm3): bool
    {
        $neededWeight = $weightKg instanceof BigDecimal ? $weightKg : BigDecimal::of((string) ($weightKg ?: '0'));

        if ($this->remainingWeightKg()->isLessThan($neededWeight)) {
            return false;
        }

        if ($volumeDm3 > 0 && $this->remainingVolumeDm3() < $volumeDm3) {
            return false;
        }

        return true;
    }

    public function canAcceptDgClass(?string $newDgClass): bool
    {
        if (empty($newDgClass)) {
            return true;
        }

        // Compare against all existing loaded DG items
        foreach ($this->items()->get() as $item) {
            if (! empty($item->dg_class)) {
                if (! DgSegregationValidator::isCompatible($newDgClass, $item->dg_class)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Add an item / package to this load, enforcing FCL, DG, Reefer, and weight/volume constraints.
     */
    public function addItem(
        Shipment $shipment,
        ?Package $package,
        float|string|BigDecimal $weightKg,
        int $volumeDm3,
        ?string $dgClass = null,
        bool $isReefer = false
    ): LoadItem {
        // 1. FCL check: only exactly 1 shipment allowed per container
        if ($this->isFcl()) {
            $existingShipments = $this->items()->distinct()->pluck('shipment_id')->toArray();
            if (! empty($existingShipments) && ! in_array($shipment->id, $existingShipments, true)) {
                throw InvalidLoadConsolidationException::fclMultipleShipments($this->load_number);
            }
        }

        // 2. Reefer check
        if ($isReefer && ! $this->is_reefer) {
            throw InvalidLoadConsolidationException::reeferMismatch(
                $this->load_number,
                'Paket kargo dingin (reefer) tidak dapat dimuat ke unit non-reefer.'
            );
        }

        // 3. DG Segregation check
        if (! empty($dgClass)) {
            foreach ($this->items()->get() as $existingItem) {
                if (! empty($existingItem->dg_class)) {
                    if (! DgSegregationValidator::isCompatible($dgClass, $existingItem->dg_class)) {
                        throw InvalidLoadConsolidationException::dgIncompatible(
                            $this->load_number,
                            $dgClass,
                            $existingItem->dg_class
                        );
                    }
                }
            }
        }

        // 4. Capacity check
        $neededWeight = $weightKg instanceof BigDecimal ? $weightKg : BigDecimal::of((string) ($weightKg ?: '0'));
        if (! $this->canFit($neededWeight, $volumeDm3)) {
            throw new \RuntimeException("Muatan '{$this->load_number}' tidak memiliki cukup sisa berat/volume.");
        }

        // 5. Create LoadItem
        $seq = $this->items()->count() + 1;
        $item = LoadItem::create([
            'load_id' => $this->id,
            'shipment_id' => $shipment->id,
            'package_id' => $package?->id,
            'sequence' => $seq,
            'weight_kg' => (string) $neededWeight,
            'volume_dm3' => $volumeDm3,
            'dg_class' => $dgClass,
            'is_reefer' => $isReefer,
            'loaded_at' => now(),
        ]);

        $this->unsetRelation('items');

        // 6. Update current counters
        $newWeight = BigDecimal::of((string) ($this->current_weight_kg ?: '0.000'))->plus($neededWeight);
        $this->current_weight_kg = (string) $newWeight;
        $this->current_volume_dm3 = ((int) $this->current_volume_dm3) + $volumeDm3;
        $this->status = 'consolidating';
        $this->save();

        return $item;
    }

    /**
     * Seal container with official security seal.
     */
    public function seal(string $sealNumber): void
    {
        $this->seal_number = $sealNumber;
        $this->sealed_at = now();
        $this->status = 'sealed';
        $this->save();
    }

    /**
     * Record SOLAS Verified Gross Mass (VGM).
     */
    public function recordVgm(float|string|BigDecimal $vgmKg, string $method, string $certifiedBy): void
    {
        $this->vgm_kg = (string) ($vgmKg instanceof BigDecimal ? $vgmKg : BigDecimal::of((string) $vgmKg));
        $this->vgm_method = in_array($method, ['method_1', 'method_2'], true) ? $method : 'method_1';
        $this->vgm_certified_by = $certifiedBy;
        $this->vgm_verified_at = now();
        $this->save();
    }

    /**
     * Load unit onto schedule. Enforces mandatory SOLAS VGM for maritime vessel schedules.
     */
    public function loadOntoSchedule(Schedule $schedule): void
    {
        // SOLAS Chapter VI enforcement for containers loading onto SEA vessels
        if ($this->load_type === 'container' && $schedule->mode === TransportMode::SEA) {
            if (! $this->hasVgm()) {
                $unitIdentifier = $this->loadable?->container_number ?? "ID #{$this->loadable_id}";
                throw MissingSolasVgmException::forLoad($this->load_number, $unitIdentifier);
            }
        }

        $this->schedule_id = $schedule->id;
        $this->status = 'loaded';
        $this->save();
    }

    /**
     * Static helper to validate that a physical unit is not in two active loads simultaneously.
     */
    public static function assertUnitNotActive(string $loadableType, int $loadableId): void
    {
        $activeLoad = self::where('loadable_type', $loadableType)
            ->where('loadable_id', $loadableId)
            ->whereIn('status', ['planning', 'consolidating', 'sealed', 'loaded', 'in_transit'])
            ->first();

        if ($activeLoad) {
            $unitName = class_basename($loadableType)." #{$loadableId}";
            throw ActiveLoadConflictException::forUnit($unitName, $activeLoad->load_number);
        }
    }
}
