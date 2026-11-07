<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Contracts\WarehouseReceiptRegistryGateway;
use Modules\Mining\Domain\Enums\WarehouseReceiptStatus;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\StateMachines\WarehouseReceiptCollateralStateMachine;
use Modules\Mining\Ledger\MiningFase121LedgerPostingService;

final class ReleaseWarehouseReceiptCollateralAction
{
    public function __construct(
        private readonly WarehouseReceiptCollateralStateMachine $stateMachine,
        private readonly WarehouseReceiptRegistryGateway $registryGateway,
        private readonly MiningFase121LedgerPostingService $ledgerPostingService
    ) {}

    public function execute(
        MiningMetalsWarehouseReceipt $receipt,
        bool $isFullRelease = true,
        ?int $repaymentAmountIdr = null
    ): MiningMetalsWarehouseReceipt {
        $bankName = (string) ($receipt->pledged_bank_name ?: 'Mitra Bank SCF');
        $repayAmount = $repaymentAmountIdr ?? (int) $receipt->max_financing_limit_idr;

        // Lepas hak tanggungan di gateway Bappebti
        $this->registryGateway->releaseReceiptCollateralLien($receipt->receipt_number, $bankName);

        if ($repayAmount > 0) {
            $this->ledgerPostingService->postWarehouseReceiptScfLoanRepayment(
                receiptNumber: $receipt->receipt_number,
                repayAmountIdr: $repayAmount,
                bankName: $bankName,
                description: "Pelunasan fasilitas SCF {$bankName} agunan resi gudang {$receipt->receipt_number}"
            );
        }

        $targetStatus = $isFullRelease ? WarehouseReceiptStatus::FULLY_RELEASED : WarehouseReceiptStatus::PARTIAL_RELEASE;
        $this->stateMachine->transition($receipt, $targetStatus);

        return $receipt;
    }
}
