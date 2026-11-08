<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlTravelPass;

final class HotelMultiCurrencyPointsValuationService
{
    /** @var array<string, float> FX rate to 1 IDR */
    public const FX_RATES_TO_IDR = [
        'IDR' => 1.0,
        'USD' => 16250.0,
        'SGD' => 12100.0,
        'EUR' => 17400.0,
        'AUD' => 10600.0,
        'JPY' => 105.0,
    ];

    /**
     * @return array{
     *     travel_pass_id: string,
     *     total_points_balance: int,
     *     valuation_idr: int,
     *     multi_currency_valuation: array<string, float>,
     *     evaluated_at: string
     * }
     */
    public function evaluatePassMultiCurrencyBalance(string $travelPassId): array
    {
        $pass = HtlTravelPass::findOrFail($travelPassId);
        $points = (int) $pass->pts_balance;
        $valuationIdr = $points * 100; // 100 IDR per point base

        $multiCurrency = [];
        foreach (self::FX_RATES_TO_IDR as $currency => $rate) {
            $multiCurrency[$currency] = round($valuationIdr / $rate, 2);
        }

        return [
            'travel_pass_id' => $pass->id,
            'total_points_balance' => $points,
            'valuation_idr' => $valuationIdr,
            'multi_currency_valuation' => $multiCurrency,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
