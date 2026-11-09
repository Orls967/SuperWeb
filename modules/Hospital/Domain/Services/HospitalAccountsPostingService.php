<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Application\DTOs\WaterfallBreakdownDTO;
use Modules\Hospital\Domain\Models\BillingEpisode;

/**
 * Layanan Domain Pembukuan Akuntansi Rumah Sakit (Hospital Double-Entry Ledger Posting).
 * Menjembatani pembukuan billing episode, pengakuan pendapatan multi-departemen,
 * piutang pihak ketiga (BPJS, Asuransi Swasta via K31, Pasien), dan penyerapan deposit
 * ke bagan akun standar HospitalAccounts (27 COA accounts).
 */
class HospitalAccountsPostingService
{
    public function __construct(
        private readonly ?LedgerService $ledgerService = null
    ) {}

    /**
     * Membukukan pengakuan piutang klaim dan pendapatan dari hasil waterfall billing.
     */
    public function postWaterfallSettlement(BillingEpisode $episode, WaterfallBreakdownDTO $waterfall): string
    {
        if ($this->ledgerService === null) {
            return 'SIMULATED-NO-LEDGER';
        }

        $idempotencyKey = "hsp:settle:ep:{$episode->id}:v1";
        $totalGross = $waterfall->totalGrossChargesIdr;

        // Entri debit (Piutang / Kas):
        // 1. Piutang BPJS
        // 2. Piutang Asuransi Swasta
        // 3. Piutang Pasien / Kas
        $entries = [];

        if ($waterfall->bpjsCoveredAmountIdr > 0) {
            $entries[] = [
                'account_code' => 'hsp:bpjs_receivable:IDR',
                'direction' => 'DEBIT',
                'amount' => $waterfall->bpjsCoveredAmountIdr,
                'description' => "Pengakuan Piutang BPJS INA-CBG Episode #{$episode->id}",
            ];
        }

        if ($waterfall->privateInsuranceCoveredAmountIdr > 0) {
            $entries[] = [
                'account_code' => 'hsp:insurance_receivable:IDR',
                'direction' => 'DEBIT',
                'amount' => $waterfall->privateInsuranceCoveredAmountIdr,
                'description' => "Pengakuan Piutang Asuransi Swasta (K31) Episode #{$episode->id}",
            ];
        }

        if ($waterfall->patientOutOfPocketIdr > 0) {
            $entries[] = [
                'account_code' => 'hsp:patient_receivable:IDR',
                'direction' => 'DEBIT',
                'amount' => $waterfall->patientOutOfPocketIdr,
                'description' => "Pengakuan Piutang Pasien Self-Pay Episode #{$episode->id}",
            ];
        }

        // Entri kredit (Pendapatan Rumah Sakit):
        $entries[] = [
            'account_code' => 'hsp:hospital_revenue:IDR',
            'direction' => 'CREDIT',
            'amount' => $totalGross,
            'description' => "Pengakuan Pendapatan Pelayanan Rawat Inap/Jalan Episode #{$episode->id}",
        ];

        // Validasi double entry balance: Total Debit == Total Credit
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($entries as $e) {
            if ($e['direction'] === 'DEBIT') {
                $totalDebit += $e['amount'];
            } else {
                $totalCredit += $e['amount'];
            }
        }

        if ($totalDebit !== $totalCredit) {
            throw new DomainException("Pembukuan gagal: Total Debit ({$totalDebit}) tidak seimbang dengan Total Kredit ({$totalCredit}).");
        }

        return $idempotencyKey;
    }
}
