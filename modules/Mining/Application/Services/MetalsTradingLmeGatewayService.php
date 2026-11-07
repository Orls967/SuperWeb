<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Contracts\MetalsTradingLmeGateway;

final class MetalsTradingLmeGatewayService implements MetalsTradingLmeGateway
{
    /**
     * @return array{metal: string, cash_cents_ton: int, three_month_cents_ton: int, timestamp: string}
     */
    public function fetchCashSettlementPrice(string $metalSymbol): array
    {
        $symbol = strtoupper($metalSymbol);

        // Benchmark baseline price in cents ($16,500/ton for Ni, $9,200/ton for Cu)
        $cashPrice = match ($symbol) {
            'LME-CU', 'COPPER' => 920000,
            'LME-CO', 'COBALT' => 3100000,
            default => 1650000, // LME-NI
        };

        // Standard contango spread $120/ton
        $threeMonth = $cashPrice + 12000;

        return [
            'metal' => $symbol,
            'cash_cents_ton' => $cashPrice,
            'three_month_cents_ton' => $threeMonth,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{order_id: string, executed: bool, fill_price_cents: int, executed_at: string}
     */
    public function broadcastHedgeOrder(string $contractNumber, string $side, float $lots, int $limitCentsPerTon): array
    {
        $orderId = 'ORD-LME-'.substr(hash('sha256', $contractNumber.$side.(string) $lots), 0, 12);

        return [
            'order_id' => $orderId,
            'executed' => true,
            'fill_price_cents' => $limitCentsPerTon,
            'executed_at' => now()->toIso8601String(),
        ];
    }

    public function fetchExchangeRateUsdIdr(): int
    {
        // Kurs JISDOR acuan Rp 16.250 / USD
        return 16250;
    }
}
