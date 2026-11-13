<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Support\Posting;
use Modules\Wms\Ledger\WmsAccounts;
use Modules\Wms\Ledger\WmsTransactionTypes;

/**
 * Orkestrator posting buku besar untuk 12 kejadian uang modul WMS (D41-9).
 * Semua nominal dalam integer IDR minor units; konvensi Debit (−), Kredit (+), Σ = 0.
 */
final class WmsLedgerPoster
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * D41-1: Posting saldo awal persediaan gudang.
     */
    public function postOpeningBalance(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($warehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::OPENING_BALANCE,
            debitAccount: $whAcc,
            creditAccount: WmsAccounts::OPENING_BALANCE_EQUITY,
            amountIdr: $amountIdr,
            description: "Saldo awal persediaan gudang {$warehouseCode}",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:OPENING:{$referenceId}",
            referenceType: 'WMS_OPENING_BALANCE',
            referenceId: $referenceId,
        );
    }

    /**
     * D41-2: Penerimaan stok inbound (GRN).
     */
    public function postInboundReceipt(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($warehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::INBOUND_RECEIPT,
            debitAccount: $whAcc,
            creditAccount: WmsAccounts::RECEIVING_CLEARING,
            amountIdr: $amountIdr,
            description: "Penerimaan stok inbound gudang {$warehouseCode} ({$referenceId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:INBOUND:{$referenceId}",
            referenceType: 'WMS_RECEIPT',
            referenceId: $referenceId,
        );
    }

    /**
     * D41-3: Pengeluaran stok outbound (pick/ship).
     */
    public function postOutboundIssue(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($warehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::OUTBOUND_ISSUE,
            debitAccount: WmsAccounts::ISSUE_CLEARING,
            creditAccount: $whAcc,
            amountIdr: $amountIdr,
            description: "Pengeluaran stok outbound gudang {$warehouseCode} ({$referenceId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:OUTBOUND:{$referenceId}",
            referenceType: 'WMS_ISSUE',
            referenceId: $referenceId,
        );
    }

    /**
     * D41-4: Transfer keluar ke gudang tujuan (in-transit).
     */
    public function postTransferOut(string $originWarehouseCode, int $amountIdr, string $transferId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($originWarehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::TRANSFER_OUT,
            debitAccount: WmsAccounts::INVENTORY_IN_TRANSIT,
            creditAccount: $whAcc,
            amountIdr: $amountIdr,
            description: "Transfer keluar dari gudang {$originWarehouseCode} (#{$transferId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:TRANSFER_OUT:{$transferId}",
            referenceType: 'WMS_TRANSFER',
            referenceId: $transferId,
        );
    }

    /**
     * D41-5: Transfer masuk di gudang tujuan dari in-transit.
     */
    public function postTransferIn(string $destWarehouseCode, int $amountIdr, string $transferId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($destWarehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::TRANSFER_IN,
            debitAccount: $whAcc,
            creditAccount: WmsAccounts::INVENTORY_IN_TRANSIT,
            amountIdr: $amountIdr,
            description: "Transfer masuk ke gudang {$destWarehouseCode} (#{$transferId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:TRANSFER_IN:{$transferId}",
            referenceType: 'WMS_TRANSFER',
            referenceId: $transferId,
        );
    }

    /**
     * D41-6: Penyesuaian cycle count positif (gain).
     */
    public function postCycleCountGain(string $warehouseCode, int $amountIdr, string $cycleCountId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($warehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::CYCLE_COUNT_GAIN,
            debitAccount: $whAcc,
            creditAccount: WmsAccounts::INVENTORY_GAIN,
            amountIdr: $amountIdr,
            description: "Selisih lebih cycle count gudang {$warehouseCode} (#{$cycleCountId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:CC_GAIN:{$cycleCountId}",
            referenceType: 'WMS_CYCLE_COUNT',
            referenceId: $cycleCountId,
        );
    }

    /**
     * D41-7: Penyesuaian cycle count negatif (shrinkage).
     */
    public function postCycleCountShrinkage(string $warehouseCode, int $amountIdr, string $cycleCountId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($warehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::CYCLE_COUNT_SHRINKAGE,
            debitAccount: WmsAccounts::INVENTORY_SHRINKAGE,
            creditAccount: $whAcc,
            amountIdr: $amountIdr,
            description: "Selisih kurang cycle count gudang {$warehouseCode} (#{$cycleCountId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:CC_SHRINK:{$cycleCountId}",
            referenceType: 'WMS_CYCLE_COUNT',
            referenceId: $cycleCountId,
        );
    }

    /**
     * D41-8: Write-off stok rusak atau kadaluarsa.
     */
    public function postInventoryWriteOff(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($warehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::INVENTORY_WRITEOFF,
            debitAccount: WmsAccounts::INVENTORY_WRITEOFF,
            creditAccount: $whAcc,
            amountIdr: $amountIdr,
            description: "Penghapusan stok rusak/kadaluarsa gudang {$warehouseCode} ({$referenceId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:WRITEOFF:{$referenceId}",
            referenceType: 'WMS_WRITEOFF',
            referenceId: $referenceId,
        );
    }

    /**
     * D41-9: Transit loss (selisih transfer hilang di jalan).
     */
    public function postTransitLoss(int $amountIdr, string $transferId, ?string $idempotencyKey = null): bool
    {
        return $this->postBalanced(
            type: WmsTransactionTypes::TRANSIT_LOSS,
            debitAccount: WmsAccounts::TRANSIT_LOSS,
            creditAccount: WmsAccounts::INVENTORY_IN_TRANSIT,
            amountIdr: $amountIdr,
            description: "Kehilangan persediaan in-transit (#{$transferId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:TRANSIT_LOSS:{$transferId}",
            referenceType: 'WMS_TRANSFER',
            referenceId: $transferId,
        );
    }

    /**
     * D41-10: Revaluasi harga pokok standar (mark-up).
     */
    public function postCostRevaluationMarkUp(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($warehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::COST_REVALUATION_MARKUP,
            debitAccount: $whAcc,
            creditAccount: WmsAccounts::COST_REVALUATION_VARIANCE,
            amountIdr: $amountIdr,
            description: "Revaluasi naik harga pokok gudang {$warehouseCode} ({$referenceId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:REVAL_UP:{$referenceId}",
            referenceType: 'WMS_REVALUATION',
            referenceId: $referenceId,
        );
    }

    /**
     * D41-11: Revaluasi harga pokok standar (mark-down).
     */
    public function postCostRevaluationMarkDown(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool
    {
        $whAcc = WmsAccounts::ensureWarehouseInventoryAccount($warehouseCode);

        return $this->postBalanced(
            type: WmsTransactionTypes::COST_REVALUATION_MARKDOWN,
            debitAccount: WmsAccounts::COST_REVALUATION_VARIANCE,
            creditAccount: $whAcc,
            amountIdr: $amountIdr,
            description: "Revaluasi turun harga pokok gudang {$warehouseCode} ({$referenceId})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:REVAL_DOWN:{$referenceId}",
            referenceType: 'WMS_REVALUATION',
            referenceId: $referenceId,
        );
    }

    /**
     * D41-12: Tagihan jasa penyimpanan & handling 3PL.
     */
    public function post3PlBilling(int $amountIdr, string $billingRef, ?string $idempotencyKey = null): bool
    {
        return $this->postBalanced(
            type: WmsTransactionTypes::THREE_PL_BILLING,
            debitAccount: WmsAccounts::THREE_PL_RECEIVABLE,
            creditAccount: WmsAccounts::THREE_PL_REVENUE,
            amountIdr: $amountIdr,
            description: "Tagihan jasa 3PL ({$billingRef})",
            idempotencyKey: $idempotencyKey ?? "IDEM:WMS:3PL:{$billingRef}",
            referenceType: 'WMS_3PL_BILLING',
            referenceId: $billingRef,
        );
    }

    /**
     * Saldo buku besar aset persediaan gudang (positif = nilai aset debit netto).
     */
    public function getWarehouseLedgerBalanceIdr(string $warehouseCode): int
    {
        $code = WmsAccounts::inventoryForWarehouse($warehouseCode);
        $cached = LedgerAccount::where('code', $code)->value('cached_balance');
        if ($cached === null) {
            return 0;
        }

        // Konvensi debit = negative di ledger_accounts; nilai aset positif = -cached_balance
        return -(int) $cached;
    }

    private function postBalanced(
        WmsTransactionTypes $type,
        string $debitAccount,
        string $creditAccount,
        int $amountIdr,
        string $description,
        string $idempotencyKey,
        string $referenceType,
        string $referenceId,
    ): bool {
        if ($amountIdr < 0) {
            throw new InvalidArgumentException('Nominal posting WMS tidak boleh negatif.');
        }
        if ($amountIdr === 0) {
            return false;
        }

        if (LedgerTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
            return false;
        }

        $this->ledgerService->post(new PostingDTO(
            type: $type->value,
            description: $description,
            idempotencyKey: $idempotencyKey,
            entries: Posting::lines()
                ->debit($debitAccount, $amountIdr)
                ->credit($creditAccount, $amountIdr)
                ->toEntries('IDR'),
            referenceType: $referenceType,
            referenceId: $referenceId,
        ));

        return true;
    }
}
