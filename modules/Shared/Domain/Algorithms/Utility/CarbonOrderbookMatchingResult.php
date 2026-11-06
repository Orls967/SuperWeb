<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Hasil eksekusi matching engine orderbook karbon dan sertifikat integritas.
 */
final class CarbonOrderbookMatchingResult
{
    /**
     * @param  array<array<string, mixed>>  $matchedTrades
     * @param  array<array<string, mixed>>  $remainingBids
     * @param  array<array<string, mixed>>  $remainingAsks
     */
    public function __construct(
        public readonly array $matchedTrades,
        public readonly int $totalTradedKg,
        public readonly int $totalTradeValueIdr,
        public readonly array $remainingBids,
        public readonly array $remainingAsks,
        public readonly string $certificateHash,
        public readonly string $canonicalPayload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'matched_trades' => $this->matchedTrades,
            'total_traded_kg' => $this->totalTradedKg,
            'total_trade_value_idr' => $this->totalTradeValueIdr,
            'remaining_bids' => $this->remainingBids,
            'remaining_asks' => $this->remainingAsks,
            'certificate_hash' => $this->certificateHash,
            'canonical_payload' => $this->canonicalPayload,
        ];
    }
}
