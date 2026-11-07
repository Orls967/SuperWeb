<?php

declare(strict_types=1);

namespace Modules\Mining\Contracts;

/**
 * Kontrak G7-K15: Metals Trading & LME Feed Gateway.
 * Menghubungkan trading desk dengan bursa logam London Metal Exchange (LME)
 * dan broker lindung nilai untuk penetapan harga spot dan forward futures.
 */
interface MetalsTradingLmeGateway
{
    /**
     * Mengambil harga kuotasi resmi LME Cash dan 3-Month Forward.
     *
     * @return array{metal: string, cash_cents_ton: int, three_month_cents_ton: int, timestamp: string}
     */
    public function fetchCashSettlementPrice(string $metalSymbol): array;

    /**
     * Mengirimkan order hedging posisi short/long ke clearing broker.
     *
     * @return array{order_id: string, executed: bool, fill_price_cents: int, executed_at: string}
     */
    public function broadcastHedgeOrder(string $contractNumber, string $side, float $lots, int $limitCentsPerTon): array;

    /**
     * Mengambil kurs tengah Bank Indonesia / JISDOR untuk konversi USD ke IDR.
     */
    public function fetchExchangeRateUsdIdr(): int;
}
