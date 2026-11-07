<?php

declare(strict_types=1);

namespace Modules\Mining\Ledger;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

/**
 * Layanan Pembukuan Buku Besar (Double-Entry Ledger) Operasi Pertambangan Fase 93 (D93-7, U-3).
 *
 * Menjamin 5 kejadian akuntansi Fase 93 tercatat secara idempoten dengan arah debit/kredit eksplisit:
 * 1. Pemakaian BBM dari tangki site:
 *    Debit expense:min:fuel:IDR | Kredit min:fuel_inventory:IDR
 * 2. Tagihan kontraktor angkut (ton-km x tarif):
 *    Debit expense:min:hauling_contractor:IDR | Kredit ap:min:contractor:{party}:IDR
 * 3. Penahanan (Hold) bayaran kontraktor akibat anomali BBM:
 *    Debit ap:min:contractor:{party}:IDR | Kredit min:contractor_payment_hold:IDR
 * 4. Rilis penahanan bayaran kontraktor (sanggahan diterima):
 *    Debit min:contractor_payment_hold:IDR | Kredit ap:min:contractor:{party}:IDR
 * 5. Pemotongan permanen varians BBM (anomali terbukti):
 *    Debit min:contractor_payment_hold:IDR | Kredit min:fuel_variance_recovery:IDR
 */
final class MiningFase93LedgerPostingService
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * 1. Posting pemakaian BBM dari tangki penyimpanan site ke unit alat berat.
     */
    public function postFuelDispense(
        string $dispenseRecordId,
        string $dispenseCode,
        string $equipmentCode,
        string $storageCode,
        int $amountIdr
    ): string {
        $this->assertPositiveAmount($amountIdr, 'pemakaian solar');

        $dto = new PostingDTO(
            type: 'MINING_FUEL_EXPENSE',
            description: "Pemakaian solar {$equipmentCode} dari {$storageCode}",
            idempotencyKey: "min:fuel-dispense:{$dispenseRecordId}",
            entries: Posting::lines()
                ->debit(MiningAccounts::fuelExpense(), $amountIdr, 'IDR')
                ->credit(MiningAccounts::fuelInventory(), $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_FUEL_DISPENSE',
            referenceId: $dispenseCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-FUEL-{$dispenseCode}";
    }

    /**
     * 2. Posting tagihan kontraktor angkut berdasarkan realisasi ton-km shift.
     */
    public function postContractorHaulingBilling(
        string $contractorPartyId,
        string $siteCode,
        string $shiftDate,
        string $shiftCode,
        int $amountIdr,
        string $idempotencyKey
    ): string {
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Idempotency-Key wajib diisi untuk pembukuan tagihan kontraktor angkut.');
        }

        $this->assertPositiveAmount($amountIdr, 'tagihan kontraktor angkut');

        $contractorAccount = MiningAccounts::contractorPayable($contractorPartyId);
        $expenseAccount = 'expense:min:hauling_contractor:IDR';

        $dto = new PostingDTO(
            type: 'MINING_HAUL_BILLING',
            description: "Tagihan kontraktor angkut {$contractorPartyId} shift {$shiftDate} {$shiftCode}",
            idempotencyKey: $idempotencyKey,
            entries: Posting::lines()
                ->debit($expenseAccount, $amountIdr, 'IDR')
                ->credit($contractorAccount, $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_HAUL_SHIFT',
            referenceId: "BILL-{$siteCode}-{$shiftDate}-{$shiftCode}",
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : 'JRN-SIMULATED-93';
    }

    /**
     * 3. Posting penahanan (hold) pembayaran kontraktor akibat temuan anomali BBM M3.
     */
    public function postContractorFuelHold(
        string $caseId,
        string $caseNumber,
        string $contractorPartyId,
        string $shiftDate,
        int $heldAmountIdr
    ): array {
        $this->assertPositiveAmount($heldAmountIdr, 'penahanan anomali BBM');

        $contractorAccount = MiningAccounts::contractorPayable($contractorPartyId);
        $holdAccount = 'min:contractor_payment_hold:IDR';
        $idempotencyKey = "min:fuel-hold:{$caseId}";

        $dto = new PostingDTO(
            type: 'MINING_FUEL_HOLD',
            description: "Penahanan tagihan kontraktor {$contractorPartyId} anomali BBM shift {$shiftDate}",
            idempotencyKey: $idempotencyKey,
            entries: Posting::lines()
                ->debit($contractorAccount, $heldAmountIdr, 'IDR')
                ->credit($holdAccount, $heldAmountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_FUEL_ANOMALY',
            referenceId: $caseNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return [
            'idempotency_key' => $idempotencyKey,
            'journal_id' => $journal ? (string) $journal->id : "JRN-HOLD-{$caseNumber}",
        ];
    }

    /**
     * 4. Posting rilis penahanan tagihan kontraktor saat sanggahan diterima.
     */
    public function postContractorFuelHoldRelease(
        string $caseId,
        string $caseNumber,
        string $contractorPartyId,
        int $holdAmountIdr
    ): string {
        $this->assertPositiveAmount($holdAmountIdr, 'rilis penahanan BBM');

        $contractorAccount = MiningAccounts::contractorPayable($contractorPartyId);
        $idempotencyKey = "min:fuel-release:{$caseId}";

        $dto = new PostingDTO(
            type: 'MINING_FUEL_RELEASE',
            description: "Rilis penahanan tagihan kasus {$caseNumber}",
            idempotencyKey: $idempotencyKey,
            entries: Posting::lines()
                ->debit('min:contractor_payment_hold:IDR', $holdAmountIdr, 'IDR')
                ->credit($contractorAccount, $holdAmountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_FUEL_ANOMALY',
            referenceId: $caseNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-REL-{$caseNumber}";
    }

    /**
     * 5. Posting pemotongan permanen varians BBM saat anomali terbukti definitif.
     */
    public function postContractorFuelHoldDeduction(
        string $caseId,
        string $caseNumber,
        int $holdAmountIdr
    ): string {
        $this->assertPositiveAmount($holdAmountIdr, 'pemotongan varians BBM');

        $idempotencyKey = "min:fuel-deduct:{$caseId}";

        $dto = new PostingDTO(
            type: 'MINING_FUEL_RECOVERY',
            description: "Pemulihan varians BBM definitif kasus {$caseNumber}",
            idempotencyKey: $idempotencyKey,
            entries: Posting::lines()
                ->debit('min:contractor_payment_hold:IDR', $holdAmountIdr, 'IDR')
                ->credit('min:fuel_variance_recovery:IDR', $holdAmountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_FUEL_ANOMALY',
            referenceId: $caseNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-DED-{$caseNumber}";
    }

    private function assertPositiveAmount(int $amountIdr, string $context): void
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException("Nominal posting {$context} harus bernilai positif (diterima: {$amountIdr}).");
        }
    }
}
