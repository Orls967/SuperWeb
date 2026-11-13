<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use Modules\Wms\Domain\ValueObjects\ThreePlRateCard;

/**
 * Kalkulator tagihan harian/bulanan 3PL dengan detail per komponen biaya.
 */
final class StorageBillingCalculator
{
    /**
     * @param  list<array{pallet_count: int, days: int, inbound_units: int, outbound_units: int}>  $dailyLogs
     * @return array{
     *     client_code: string,
     *     total_pallet_days: int,
     *     total_inbound_units: int,
     *     total_outbound_units: int,
     *     total_fee_idr: int
     * }
     */
    public function summarizePeriod(ThreePlRateCard $rateCard, array $dailyLogs): array
    {
        $palletDays = 0;
        $inboundUnits = 0;
        $outboundUnits = 0;

        foreach ($dailyLogs as $log) {
            $palletDays += max(0, (int) $log['pallet_count']) * max(0, (int) $log['days']);
            $inboundUnits += max(0, (int) $log['inbound_units']);
            $outboundUnits += max(0, (int) $log['outbound_units']);
        }

        $totalFee = $rateCard->computeTotalFeeIdr($palletDays, $inboundUnits, $outboundUnits);

        return [
            'client_code' => $rateCard->clientCode,
            'total_pallet_days' => $palletDays,
            'total_inbound_units' => $inboundUnits,
            'total_outbound_units' => $outboundUnits,
            'total_fee_idr' => $totalFee,
        ];
    }
}
