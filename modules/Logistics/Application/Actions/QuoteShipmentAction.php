<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;
use Modules\Logistics\Contracts\RateCardOverrideResolver;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\NoRateBracketFoundException;
use Modules\Logistics\Domain\Exceptions\NoRateCardFoundException;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Quote;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Surcharge;
use Modules\Logistics\Domain\Services\ChargeableWeightCalculator;

class QuoteShipmentAction
{
    public function __construct(
        private readonly ChargeableWeightCalculator $weightCalculator,
        private readonly ?RateCardOverrideResolver $contractRateResolver = null,
    ) {}

    /**
     * @param  array<int, array{weight_g: int, length_mm: int, width_mm: int, height_mm: int, description?: string, hs_code?: string, dg_un_number?: string, dg_class?: string, temp_min_c10?: int, temp_max_c10?: int}>  $packages
     */
    public function execute(
        User $shipper,
        int $originLocationId,
        int $destinationLocationId,
        ServiceLevel $serviceLevel,
        ?TransportMode $mode = null,
        array $packages = [],
        int $declaredValueIdr = 0,
        bool $insured = false,
        int $codAmountIdr = 0
    ): Quote {
        $mode = $mode ?? $serviceLevel->defaultMode();

        $originLoc = Location::findOrFail($originLocationId);
        $destLoc = Location::findOrFail($destinationLocationId);

        // 1. Calculate Chargeable Weight
        $weightResult = $this->weightCalculator->calculate($packages, $mode, $serviceLevel);

        // 2. Find Rate Card
        $rateCard = $this->resolveRateCard($originLoc, $destLoc, $serviceLevel, $mode, (int) $shipper->id);
        if (! $rateCard) {
            throw NoRateCardFoundException::forRoute($originLocationId, $destinationLocationId, $serviceLevel->value);
        }

        // 3. Find Rate Bracket
        $bracket = $rateCard->findBracketForWeight($weightResult->chargeableWeightKg);
        if (! $bracket) {
            throw NoRateBracketFoundException::forWeight($rateCard->id, $weightResult->chargeableWeightKg->toFloat());
        }

        // 4. Calculate Base Freight
        $calculatedBase = $bracket->calculateCost($weightResult->chargeableWeightKg);
        $minCharge = BigDecimal::of($rateCard->min_charge_idr);
        $baseFreight = $calculatedBase->compareTo($minCharge) < 0 ? $minCharge : $calculatedBase;
        $baseFreightInt = (int) $baseFreight->toInt();

        // 5. Determine applicable surcharges
        $hasDg = false;
        $hasReefer = false;
        foreach ($packages as $pkg) {
            if (! empty($pkg['dg_un_number']) || ! empty($pkg['dg_class'])) {
                $hasDg = true;
            }
            if (isset($pkg['temp_min_c10']) || isset($pkg['temp_max_c10'])) {
                $hasReefer = true;
            }
        }

        $surchargeContext = [
            'declared_value_idr' => $declaredValueIdr,
            'cod_amount_idr' => $codAmountIdr,
            'container_count' => 1,
        ];

        $surchargesBreakdown = [];
        $totalSurcharges = BigDecimal::zero();

        $activeSurcharges = Surcharge::where('is_active', true)->get();
        foreach ($activeSurcharges as $surcharge) {
            $applies = match ($surcharge->code) {
                Surcharge::CODE_FUEL, Surcharge::CODE_BAF, Surcharge::CODE_PEAK, Surcharge::CODE_DOC_FEE => true,
                Surcharge::CODE_DG => $hasDg,
                Surcharge::CODE_REEFER => $hasReefer,
                Surcharge::CODE_INSURANCE => $insured && $declaredValueIdr > 0,
                // Fee COD dipotong dari dana COD saat settlement (lgx:settle-cod), bukan ditagihkan di quote.
                Surcharge::CODE_COD_FEE => false,
                default => false,
            };

            if ($applies) {
                $surchargeCost = $surcharge->calculate($baseFreight, $surchargeContext);
                if ($surchargeCost->isPositive()) {
                    $costInt = (int) $surchargeCost->toInt();
                    $surchargesBreakdown[] = [
                        'code' => $surcharge->code,
                        'name' => $surcharge->name,
                        'amount_idr' => $costInt,
                    ];
                    $totalSurcharges = $totalSurcharges->plus($surchargeCost);
                }
            }
        }

        $totalSurchargesInt = (int) $totalSurcharges->toInt();
        $subtotal = $baseFreight->plus($totalSurcharges);
        $subtotalInt = (int) $subtotal->toInt();

        // 6. Tax (VAT 11% Simulation)
        $vatRate = (float) config('logistics.vat_rate', 0.11);
        $vatAmount = $subtotal->multipliedBy(BigDecimal::of((string) $vatRate))->toScale(0, RoundingMode::HalfUp);
        $vatAmountInt = (int) $vatAmount->toInt();

        $totalAmount = $subtotal->plus($vatAmount);
        $totalAmountInt = (int) $totalAmount->toInt();

        // 7. Create Quote model with 15-minute timelock
        $uuid = (string) Str::uuid();
        $expiresAt = now()->addMinutes(15);

        $quote = new Quote([
            'uuid' => $uuid,
            'shipper_id' => $shipper->id,
            'origin_location_id' => $originLocationId,
            'destination_location_id' => $destinationLocationId,
            'service_level' => $serviceLevel,
            'mode' => $mode,
            'declared_value_idr' => $declaredValueIdr,
            'insured' => $insured,
            'cod_amount_idr' => $codAmountIdr,
            'packages_payload' => $packages,
            'actual_weight_kg' => (string) $weightResult->actualWeightKg->toScale(4, RoundingMode::HalfUp),
            'chargeable_weight_kg' => (string) $weightResult->chargeableWeightKg->toScale(4, RoundingMode::HalfUp),
            'base_freight_idr' => $baseFreightInt,
            'surcharges_breakdown' => $surchargesBreakdown,
            'total_surcharges_idr' => $totalSurchargesInt,
            'subtotal_idr' => $subtotalInt,
            'vat_rate' => $vatRate,
            'vat_amount_idr' => $vatAmountInt,
            'total_amount_idr' => $totalAmountInt,
            'expires_at' => $expiresAt,
            'is_booked' => false,
        ]);

        $quote->payload_hash = $quote->computePayloadHash();
        $quote->save();

        return $quote;
    }

    private function resolveRateCard(
        Location $origin,
        Location $dest,
        ServiceLevel $serviceLevel,
        TransportMode $mode,
        ?int $shipperId = null
    ): ?RateCard {
        // 29.6 — rate card kontrak memenangkan tarif standar bila resolver terikat.
        if ($shipperId !== null && $this->contractRateResolver !== null) {
            $overrideId = $this->contractRateResolver->resolve($shipperId, $serviceLevel->value, $mode->value);
            if ($overrideId !== null) {
                $overrideCard = RateCard::query()
                    ->whereKey($overrideId)
                    ->where('is_active', true)
                    ->first();

                if ($overrideCard !== null) {
                    return $overrideCard;
                }
            }
        }

        // Direct location pair match
        $card = RateCard::query()
            ->where('is_active', true)
            ->where('origin_location_id', $origin->id)
            ->where('destination_location_id', $dest->id)
            ->where('service_level', $serviceLevel->value)
            ->where('mode', $mode->value)
            ->whereDate('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', now());
            })
            ->first();

        if ($card) {
            return $card;
        }

        // Zone-based fallback
        if ($origin->province && $dest->province) {
            return RateCard::query()
                ->where('is_active', true)
                ->whereNull('origin_location_id')
                ->whereNull('destination_location_id')
                ->where('origin_zone', $origin->province)
                ->where('destination_zone', $dest->province)
                ->where('service_level', $serviceLevel->value)
                ->where('mode', $mode->value)
                ->whereDate('valid_from', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', now());
                })
                ->first();
        }

        return null;
    }
}
