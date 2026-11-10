<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms\Certificates;

use JsonSerializable;

final class BilateralEliminationCertificate implements JsonSerializable
{
    /**
     * @param  array<int, string>  $periods
     * @param  array<int, array<string, mixed>>  $matrixDetails
     */
    public function __construct(
        public readonly array $periods,
        public readonly int $matchedPairCount,
        public readonly int $totalEliminatedBalanceIdr,
        public readonly int $totalEliminatedPnlIdr,
        public readonly int $totalInTransitIdr,
        public readonly int $totalDiscrepancyIdr,
        public readonly array $matrixDetails,
        public string $sha256 = ''
    ) {
        if ($this->sha256 === '') {
            $this->sha256 = $this->computeHash();
        }
    }

    public function computeHash(): string
    {
        $payload = [
            'matched_pair_count' => $this->matchedPairCount,
            'periods' => $this->periods,
            'total_discrepancy_idr' => $this->totalDiscrepancyIdr,
            'total_eliminated_balance_idr' => $this->totalEliminatedBalanceIdr,
            'total_eliminated_pnl_idr' => $this->totalEliminatedPnlIdr,
            'total_in_transit_idr' => $this->totalInTransitIdr,
        ];
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function jsonSerialize(): array
    {
        return [
            'periods' => $this->periods,
            'matched_pair_count' => $this->matchedPairCount,
            'total_eliminated_balance_idr' => $this->totalEliminatedBalanceIdr,
            'total_eliminated_pnl_idr' => $this->totalEliminatedPnlIdr,
            'total_in_transit_idr' => $this->totalInTransitIdr,
            'total_discrepancy_idr' => $this->totalDiscrepancyIdr,
            'matrix_details' => $this->matrixDetails,
            'sha256' => $this->sha256,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
