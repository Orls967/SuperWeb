<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

/**
 * Data Transfer Object untuk hasil eksekusi roll-up konsolidasi berjenjang (IFRS 10 / PSAK 65).
 */
class ConsolidationTreeResultDTO
{
    /**
     * @param  array<string, array<string, int>>  $entityTrialBalances
     * @param array<int, array{
     *     journal_code: string,
     *     elimination_type: string,
     *     debit_account: string,
     *     credit_account: string,
     *     amount_minor: int,
     *     affected_entities: array<string>,
     *     rationale: string
     * }> $eliminationJournals
     * @param array{
     *     consolidated_assets_minor: int,
     *     consolidated_liabilities_minor: int,
     *     consolidated_equity_minor: int,
     *     consolidated_revenue_minor: int,
     *     consolidated_expenses_minor: int,
     *     consolidated_net_profit_minor: int,
     *     parent_share_net_profit_minor: int,
     *     nci_share_net_profit_minor: int,
     *     fctr_reserve_minor: int,
     *     is_balance_sheet_balanced: bool
     * } $consolidatedFinancials
     * @param array<string, array{
     *     entity_code: string,
     *     effective_ownership_bps: int,
     *     nci_bps: int,
     *     allocated_profit_minor: int,
     *     allocated_equity_minor: int
     * }> $nciAttributions
     */
    public function __construct(
        public readonly string $consolidationRunId,
        public readonly string $fiscalPeriod,
        public readonly string $presentationCurrency,
        public readonly string $holdingEntityCode,
        public readonly array $entityTrialBalances,
        public readonly array $eliminationJournals,
        public readonly array $consolidatedFinancials,
        public readonly array $nciAttributions,
        public readonly string $generatedAt,
        public readonly string $integrityHash
    ) {}

    /**
     * Konversi ke array untuk representasi data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'consolidation_run_id' => $this->consolidationRunId,
            'fiscal_period' => $this->fiscalPeriod,
            'presentation_currency' => $this->presentationCurrency,
            'holding_entity_code' => $this->holdingEntityCode,
            'entities_count' => count($this->entityTrialBalances),
            'elimination_journals_count' => count($this->eliminationJournals),
            'elimination_journals' => $this->eliminationJournals,
            'consolidated_financials' => $this->consolidatedFinancials,
            'nci_attributions' => $this->nciAttributions,
            'generated_at' => $this->generatedAt,
            'integrity_hash' => $this->integrityHash,
        ];
    }
}
