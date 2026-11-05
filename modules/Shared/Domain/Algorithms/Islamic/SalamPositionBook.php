<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class SalamPositionBook
{
    /**
     * Mengevaluasi buku posisi komoditas Salam dan Parallel Salam untuk mengkalkulasi
     * Net Open Position (NOP), mark-to-market PnL, dan verifikasi kepatuhan rasio serah.
     *
     * @param  array<int, array{contract_id: string, commodity_code: string, grade: string, quantity_ordered: int, quantity_delivered: int, advance_payment_cents: int, promised_delivery_date: string}>  $salamContracts
     * @param  array<int, array{hedge_id: string, commodity_code: string, quantity_hedged: int, agreed_sale_price_cents: int, delivery_date: string}>  $parallelHedges
     * @param  int  $spotPricePerUnitCents  Harga pasar aktual komoditas per unit (dalam minor units / cents)
     */
    public function evaluate(
        string $commodityCode,
        array $salamContracts,
        array $parallelHedges,
        int $spotPricePerUnitCents,
        ?CarbonImmutable $evaluatedAt = null
    ): SalamPositionBookResult {
        if ($spotPricePerUnitCents <= 0) {
            throw new InvalidArgumentException('Harga spot komoditas harus bernilai positif.');
        }

        $now = $evaluatedAt ?? CarbonImmutable::now();
        $totalLongQuantity = 0;
        $totalDeliveredQuantity = 0;
        $totalLongCostCents = 0;

        foreach ($salamContracts as $salam) {
            if ($salam['commodity_code'] !== $commodityCode) {
                continue;
            }
            if ($salam['quantity_ordered'] <= 0 || $salam['advance_payment_cents'] <= 0) {
                throw new InvalidArgumentException('Kuantum dan pembayaran di muka kontrak salam harus positif.');
            }
            $totalLongQuantity += $salam['quantity_ordered'];
            $totalDeliveredQuantity += max(0, $salam['quantity_delivered']);
            $totalLongCostCents += $salam['advance_payment_cents'];
        }

        $totalShortQuantity = 0;
        $totalShortCommitmentCents = 0;

        foreach ($parallelHedges as $hedge) {
            if ($hedge['commodity_code'] !== $commodityCode) {
                continue;
            }
            if ($hedge['quantity_hedged'] <= 0 || $hedge['agreed_sale_price_cents'] <= 0) {
                throw new InvalidArgumentException('Kuantum dan harga jual kontrak parallel salam harus positif.');
            }
            $totalShortQuantity += $hedge['quantity_hedged'];
            $totalShortCommitmentCents += $hedge['agreed_sale_price_cents'];
        }

        // Larangan short selling komoditas: short hedge tidak boleh melebihi total komitmen salam long
        if ($totalShortQuantity > $totalLongQuantity) {
            throw new InvalidArgumentException('Short selling terlarang: total komitmen parallel salam melebihi komoditas salam yang dibeli.');
        }

        $netOpenPositionQuantity = $totalLongQuantity - $totalShortQuantity;
        $markToMarketValueCents = $totalLongQuantity * $spotPricePerUnitCents;

        // Unrealized PnL:
        // - Untuk porsi yang di-hedge: (harga jual kesepakatan short - modal pokok porsi terkait)
        // - Untuk porsi net open (unhedged): (nilai MTM porsi unhedged - modal pokok porsi unhedged)
        $avgCostPerUnit = $totalLongQuantity > 0 ? (int) intdiv($totalLongCostCents, $totalLongQuantity) : 0;
        $hedgedCostCents = $totalShortQuantity * $avgCostPerUnit;
        $hedgedPnlCents = $totalShortCommitmentCents - $hedgedCostCents;

        $unhedgedCostCents = $totalLongCostCents - $hedgedCostCents;
        $unhedgedMtmCents = $netOpenPositionQuantity * $spotPricePerUnitCents;
        $unhedgedPnlCents = $unhedgedMtmCents - $unhedgedCostCents;

        $unrealizedPnlCents = $hedgedPnlCents + $unhedgedPnlCents;

        $fulfillmentRatioBps = $totalLongQuantity > 0
            ? (int) min(10000, intdiv($totalDeliveredQuantity * 10000, $totalLongQuantity))
            : 0;

        $evaluatedAtIso = $now->toIso8601String();

        $payload = [
            'commodityCode' => $commodityCode,
            'totalLongQuantity' => $totalLongQuantity,
            'totalShortQuantity' => $totalShortQuantity,
            'netOpenPositionQuantity' => $netOpenPositionQuantity,
            'totalLongCostCents' => $totalLongCostCents,
            'totalShortCommitmentCents' => $totalShortCommitmentCents,
            'markToMarketValueCents' => $markToMarketValueCents,
            'unrealizedPnlCents' => $unrealizedPnlCents,
            'fulfillmentRatioBps' => $fulfillmentRatioBps,
            'evaluatedAt' => $evaluatedAtIso,
        ];

        $certificateHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return new SalamPositionBookResult(
            commodityCode: $commodityCode,
            totalLongQuantity: $totalLongQuantity,
            totalShortQuantity: $totalShortQuantity,
            netOpenPositionQuantity: $netOpenPositionQuantity,
            totalLongCostCents: $totalLongCostCents,
            totalShortCommitmentCents: $totalShortCommitmentCents,
            markToMarketValueCents: $markToMarketValueCents,
            unrealizedPnlCents: $unrealizedPnlCents,
            fulfillmentRatioBps: $fulfillmentRatioBps,
            contractSummaries: [
                'salam_count' => count($salamContracts),
                'hedge_count' => count($parallelHedges),
                'spot_price_cents' => $spotPricePerUnitCents,
            ],
            evaluatedAt: $evaluatedAtIso,
            certificateHash: $certificateHash,
        );
    }
}
