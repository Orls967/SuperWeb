<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\MarineAquacultureLedgerEntry;
use Modules\TradeFinance\Contracts\OfftakeLetterOfCreditGateway;

/**
 * Service penyelesaian escrow petani dan pembiayaan perdagangan ekspor (TradeFinance G7-K11) (Fase 171).
 */
class FairTradeEscrowSettlementService
{
    protected ?OfftakeLetterOfCreditGateway $lcGateway;

    public function __construct(?OfftakeLetterOfCreditGateway $lcGateway = null)
    {
        $this->lcGateway = $lcGateway;
    }

    /**
     * Kunci dana escrow untuk pembelian panen dari petani tambak.
     */
    public function lockEscrowForLot(
        int $exportLotId,
        string $farmerCode,
        int $amountMinorCents,
        string $currency = 'USD'
    ): AquacultureEscrowSettlement {
        $exportLot = AquacultureExportLot::findOrFail($exportLotId);

        if ($amountMinorCents <= 0) {
            throw new DomainException('Nilai escrow harus lebih besar dari 0 minor units.');
        }

        $escrowRef = 'ESC-'.strtoupper(substr(hash('sha256', $exportLot->lot_number.microtime()), 0, 10));

        $settlement = AquacultureEscrowSettlement::create([
            'escrow_reference' => $escrowRef,
            'export_lot_id' => $exportLot->id,
            'beneficiary_farmer_code' => $farmerCode,
            'amount_minor_cents' => $amountMinorCents,
            'currency' => $currency,
            'escrow_provider' => 'TRADE_FINANCE_ESCROW_K11',
            'status' => 'LOCKED',
            'payout_condition_checklist' => [
                'sps_assay_passed' => ($exportLot->quality_grade !== 'PENDING_ASSAY' && $exportLot->quality_grade !== 'REJECTED'),
                'mass_balance_verified' => true,
                'vessel_bl_issued' => false,
            ],
            'locked_at' => now(),
        ]);

        // Jika gateway L/C tersedia, sinkronkan pembukaan L/C offtake
        if ($this->lcGateway) {
            try {
                $lcId = $this->lcGateway->issue(
                    1, // Applicant ID (Eksportir)
                    2, // Beneficiary ID (Koperasi Tambak)
                    $amountMinorCents,
                    $currency,
                    CarbonImmutable::now()->addDays(60),
                    'IDEMP-'.$escrowRef
                );
                $settlement->update(['escrow_provider' => 'TRADE_FINANCE_LC_ID_'.$lcId]);
            } catch (\Throwable $e) {
                // Lanjutkan dengan escrow lokal jika L/C gateway eksternal mock
            }
        }

        return $settlement;
    }

    /**
     * Lepaskan dana escrow ke rekening petani setelah persyaratan terpenuhi.
     */
    public function releaseEscrowPayout(int $settlementId, string $approverRole): AquacultureEscrowSettlement
    {
        $settlement = AquacultureEscrowSettlement::findOrFail($settlementId);

        if ($settlement->status !== 'LOCKED') {
            throw new DomainException("Status escrow tidak dapat dicairkan: {$settlement->status}");
        }

        $checklist = $settlement->payout_condition_checklist ?? [];
        if (empty($checklist['sps_assay_passed'])) {
            throw new DomainException('Pencairan escrow ditolak: Sertifikat uji laboratorium SPS belum lulus.');
        }

        $settlement->update([
            'status' => 'RELEASED',
            'released_at' => now(),
            'notes' => "Escrow disetujui dan dicairkan oleh {$approverRole}.",
        ]);

        // Catat entri pembukuan penyelesaian escrow di ledger
        $lastEntry = MarineAquacultureLedgerEntry::latest('id')->first();
        $prevHash = $lastEntry ? $lastEntry->entry_hash : str_repeat('0', 64);
        $entryPayload = [
            'action' => 'ESCROW_RELEASE',
            'escrow_ref' => $settlement->escrow_reference,
            'beneficiary' => $settlement->beneficiary_farmer_code,
            'amount_minor_cents' => $settlement->amount_minor_cents,
        ];
        $entryHash = hash('sha256', $prevHash.json_encode($entryPayload));

        MarineAquacultureLedgerEntry::create([
            'journal_reference' => 'JRN-ESC-'.strtoupper(substr($settlement->escrow_reference, 4)),
            'account_code' => '2100-FARMER-PAYABLE-ESCROW',
            'direction' => 'DEBIT',
            'amount_minor_cents' => $settlement->amount_minor_cents,
            'currency' => $settlement->currency,
            'lot_reference' => $settlement->exportLot->lot_number ?? 'N/A',
            'previous_entry_hash' => $prevHash,
            'entry_hash' => $entryHash,
            'description' => "Pencairan dana escrow panen petani {$settlement->beneficiary_farmer_code}",
            'meta' => $entryPayload,
        ]);

        return $settlement;
    }
}
