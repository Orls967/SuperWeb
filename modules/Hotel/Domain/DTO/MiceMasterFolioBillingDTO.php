<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\DTO;

final class MiceMasterFolioBillingDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $subFolios
     * @param  array<int, array<string, mixed>>  $ledgerEntries
     */
    public function __construct(
        public readonly string $masterFolioId,
        public readonly string $contractNumber,
        public readonly string $corporatePayerName,
        public readonly int $totalMasterCharges,
        public readonly int $totalIndividualCharges,
        public readonly int $totalCreditsDeposits,
        public readonly int $netBalanceDue,
        public readonly array $subFolios,
        public readonly array $ledgerEntries,
        public readonly bool $isBalanced = false
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'master_folio_id' => $this->masterFolioId,
            'contract_number' => $this->contractNumber,
            'corporate_payer_name' => $this->corporatePayerName,
            'total_master_charges' => $this->totalMasterCharges,
            'total_individual_charges' => $this->totalIndividualCharges,
            'total_credits_deposits' => $this->totalCreditsDeposits,
            'net_balance_due' => $this->netBalanceDue,
            'sub_folios' => $this->subFolios,
            'ledger_entries' => $this->ledgerEntries,
            'is_balanced' => $this->isBalanced,
        ];
    }
}
