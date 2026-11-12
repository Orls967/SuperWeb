<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

/**
 * Data Transfer Object untuk hasil kalkulasi cascade dividen dan potongan pajak penghasilan.
 */
class NciCascadeResultDTO
{
    /**
     * @param array<int, array{
     *     shareholder_type: string,
     *     shareholder_name: string,
     *     ownership_bps: int,
     *     gross_dividend_minor: int,
     *     wht_rate_bps: int,
     *     wht_tax_minor: int,
     *     net_dividend_payable_minor: int,
     *     is_eliminated_in_consolidation: bool,
     *     tax_treaty_applied: ?string
     * }> $shareholderDistributions
     * @param array{
     *     total_declared_gross_minor: int,
     *     parent_share_gross_minor: int,
     *     nci_share_gross_minor: int,
     *     total_wht_withheld_minor: int,
     *     total_net_cash_outflow_minor: int,
     *     statutory_reserve_retained_minor: int
     * } $summaryMetrics
     * @param array<int, array{
     *     entry_type: string,
     *     debit_account: string,
     *     credit_account: string,
     *     amount_minor: int,
     *     memo: string
     * }> $ledgerEntries
     */
    public function __construct(
        public readonly string $declarationCode,
        public readonly string $distributingEntityCode,
        public readonly string $fiscalYear,
        public readonly string $currency,
        public readonly array $shareholderDistributions,
        public readonly array $summaryMetrics,
        public readonly array $ledgerEntries,
        public readonly string $calculatedAt,
        public readonly string $authorizationHash
    ) {}

    /**
     * Konversi ke array untuk representasi data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'declaration_code' => $this->declarationCode,
            'distributing_entity_code' => $this->distributingEntityCode,
            'fiscal_year' => $this->fiscalYear,
            'currency' => $this->currency,
            'distributions_count' => count($this->shareholderDistributions),
            'shareholder_distributions' => $this->shareholderDistributions,
            'summary_metrics' => $this->summaryMetrics,
            'ledger_entries' => $this->ledgerEntries,
            'calculated_at' => $this->calculatedAt,
            'authorization_hash' => $this->authorizationHash,
        ];
    }
}
