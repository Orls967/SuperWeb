<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

/**
 * Data Transfer Object untuk hasil kalkulasi multilateral netting intercompany.
 */
class NettingMatrixResultDTO
{
    /**
     * @param  array<string, int>  $grossPayablesPerEntity
     * @param  array<string, int>  $grossReceivablesPerEntity
     * @param  array<string, int>  $netPositionsPerEntity
     * @param array<int, array{
     *     from_entity: string,
     *     to_entity: string,
     *     settlement_amount_minor: int,
     *     currency: string,
     *     clearing_bank_ref: ?string,
     *     settlement_instruction_code: string
     * }> $settlementInstructions
     * @param array{
     *     total_gross_obligations_minor: int,
     *     total_net_settlement_minor: int,
     *     eliminated_circular_debt_minor: int,
     *     settlement_efficiency_ratio_bps: int,
     *     is_zero_sum_balanced: bool
     * } $nettingMetrics
     */
    public function __construct(
        public readonly string $nettingSessionCode,
        public readonly string $settlementPeriod,
        public readonly string $baseCurrency,
        public readonly array $grossPayablesPerEntity,
        public readonly array $grossReceivablesPerEntity,
        public readonly array $netPositionsPerEntity,
        public readonly array $settlementInstructions,
        public readonly array $nettingMetrics,
        public readonly string $computedAt,
        public readonly string $signatureProof
    ) {}

    /**
     * Konversi ke array untuk representasi data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'netting_session_code' => $this->nettingSessionCode,
            'settlement_period' => $this->settlementPeriod,
            'base_currency' => $this->baseCurrency,
            'participating_entities_count' => count($this->netPositionsPerEntity),
            'gross_payables' => $this->grossPayablesPerEntity,
            'gross_receivables' => $this->grossReceivablesPerEntity,
            'net_positions' => $this->netPositionsPerEntity,
            'settlement_instructions' => $this->settlementInstructions,
            'netting_metrics' => $this->nettingMetrics,
            'computed_at' => $this->computedAt,
            'signature_proof' => $this->signatureProof,
        ];
    }
}
