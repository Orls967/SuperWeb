<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\OverlappingRateCardException;

class RateCard extends LogisticsEntity
{
    protected $table = 'lgx_rate_cards';

    protected $fillable = [
        'name',
        'origin_location_id',
        'destination_location_id',
        'origin_zone',
        'destination_zone',
        'service_level',
        'mode',
        'min_charge_idr',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'origin_location_id' => 'integer',
        'destination_location_id' => 'integer',
        'service_level' => ServiceLevel::class,
        'mode' => TransportMode::class,
        'min_charge_idr' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(RateBracket::class, 'rate_card_id')->orderBy('min_weight_kg');
    }

    /**
     * Validate that this rate card does not overlap in time with any other active rate card for the same lane key.
     */
    public function validateNoOverlap(): void
    {
        if (! $this->is_active) {
            return;
        }

        $serviceLevelValue = $this->service_level instanceof ServiceLevel
            ? $this->service_level->value
            : (string) $this->service_level;

        $modeValue = $this->mode instanceof TransportMode
            ? $this->mode->value
            : (string) $this->mode;

        $query = self::query()
            ->where('is_active', true)
            ->where('service_level', $serviceLevelValue)
            ->where('mode', $modeValue);

        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }

        // Match origin
        if ($this->origin_location_id !== null) {
            $query->where('origin_location_id', $this->origin_location_id);
        } else {
            $query->whereNull('origin_location_id')->where('origin_zone', $this->origin_zone);
        }

        // Match destination
        if ($this->destination_location_id !== null) {
            $query->where('destination_location_id', $this->destination_location_id);
        } else {
            $query->whereNull('destination_location_id')->where('destination_zone', $this->destination_zone);
        }

        $existingCards = $query->get();

        $from = Carbon::parse($this->valid_from)->startOfDay();
        $to = $this->valid_to ? Carbon::parse($this->valid_to)->endOfDay() : null;

        foreach ($existingCards as $existing) {
            $existFrom = Carbon::parse($existing->valid_from)->startOfDay();
            $existTo = $existing->valid_to ? Carbon::parse($existing->valid_to)->endOfDay() : null;

            // Overlap check: (D is null or D >= A) and (B is null or B >= C)
            $condition1 = ($to === null) || $to->gte($existFrom);
            $condition2 = ($existTo === null) || $existTo->gte($from);

            if ($condition1 && $condition2) {
                $laneKey = sprintf(
                    '%s -> %s [%s/%s]',
                    $this->origin_location_id ?? $this->origin_zone,
                    $this->destination_location_id ?? $this->destination_zone,
                    $serviceLevelValue,
                    $modeValue
                );

                throw OverlappingRateCardException::forLane(
                    $laneKey,
                    $existing->valid_from->format('Y-m-d'),
                    $existing->valid_to?->format('Y-m-d')
                );
            }
        }
    }

    /**
     * Find matching bracket for given weight in kilograms.
     */
    public function findBracketForWeight(float|int|string|BigDecimal $weightKg): ?RateBracket
    {
        $weight = $weightKg instanceof BigDecimal
            ? $weightKg->toFloat()
            : (float) $weightKg;

        return $this->brackets
            ->first(function (RateBracket $bracket) use ($weight) {
                $min = (float) $bracket->min_weight_kg;
                $max = $bracket->max_weight_kg !== null ? (float) $bracket->max_weight_kg : null;

                if ($weight < $min) {
                    return false;
                }

                if ($max !== null && $weight > $max) {
                    return false;
                }

                return true;
            });
    }

    protected static function booted(): void
    {
        static::saving(function (self $card) {
            $card->validateNoOverlap();
        });
    }
}
