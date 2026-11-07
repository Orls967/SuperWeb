<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Contracts\WarehouseReceiptRegistryGateway;
use Modules\Mining\Domain\Enums\WarehouseReceiptStatus;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\StateMachines\WarehouseReceiptCollateralStateMachine;
use Modules\Mining\Ledger\MiningFase121LedgerPostingService;

final class PledgeWarehouseReceiptBankScfAction
{
    public function __construct(
        private readonly WarehouseReceiptCollateralStateMachine $stateMachine,
        private readonly WarehouseReceiptRegistryGateway $registryGateway,
        private readonly MiningFase121LedgerPostingService $ledgerPostingService
    ) {}

    public function execute(
        MiningMetalsWarehouseReceipt $receipt,
        string $bankPartnerName,
        ?int $drawdownLoanAmountIdr = null
    ): MiningMetalsWarehouseReceipt {
        if (empty($bankPartnerName)) {
            throw new InvalidArgumentException('Nama bank mitra SCF (bankPartnerName) wajib diisi.');
        }

        $receipt->pledged_bank_name = $bankPartnerName;
        $receipt->scf_facility_reference = 'SCF-'.now()->format('Ymd').'-'.substr(strtoupper(md5($receipt->receipt_number)), 0, 8);
        $receipt->save();

        // Verifikasi hak fidusia di gateway
        $this->registryGateway->verifyReceiptCollateralLien($receipt->receipt_number, $bankPartnerName);

        // Transisi status ke PLEDGED_SCF
        $this->stateMachine->transition($receipt, WarehouseReceiptStatus::PLEDGED_SCF);

        // Posting pencairan pinjaman SCF ke rekening bank
        $loanAmount = $drawdownLoanAmountIdr ?? (int) $receipt->max_financing_limit_idr;
        if ($loanAmount > 0) {
            $this->ledgerPostingService->postWarehouseReceiptScfLoanDrawdown(
                receiptNumber: $receipt->receipt_number,
                loanAmountIdr: $loanAmount,
                bankName: $bankPartnerName,
                description: "Pencairan modal kerja SCF {$bankPartnerName} agunan resi gudang {$receipt->receipt_number}"
            );
        }

        return $receipt;
    }
}
