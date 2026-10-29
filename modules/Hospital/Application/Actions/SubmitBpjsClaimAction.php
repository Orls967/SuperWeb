<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Ledger\HospitalTransactionTypes;

class SubmitBpjsClaimAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    public function execute(OutpatientInvoice $invoice, string $inaCbgCode, int $packageTariffIdr): BpjsClaim
    {
        return DB::transaction(function () use ($invoice, $inaCbgCode, $packageTariffIdr) {
            $variance = $packageTariffIdr - $invoice->total_amount_idr;
            $claimCode = sprintf('CLM-BPJS-%s-%04d', now()->format('Ymd'), (BpjsClaim::count() + 1));

            $claim = BpjsClaim::create([
                'claim_code' => $claimCode,
                'invoice_id' => $invoice->id,
                'ina_cbg_code' => $inaCbgCode,
                'ina_cbg_tariff_idr' => $packageTariffIdr,
                'hospital_tariff_idr' => $invoice->total_amount_idr,
                'variance_amount_idr' => $variance,
                'patient_copay_amount_idr' => 0,
                'status' => 'SUBMITTED',
            ]);

            $invoice->update([
                'ina_cbg_package_code' => $inaCbgCode,
                'ina_cbg_tariff_idr' => $packageTariffIdr,
                'status' => 'CLAIMED',
            ]);

            if ($invoice->encounter) {
                $invoice->encounter->update(['status' => 'CLAIMED']);
            }

            // Posting double-entry klaim JKN BPJS:
            // Debit Piutang BPJS (sebesar tarif paket INA-CBG)
            // Kredit Jasa Dokter, Kredit Farmasi
            // Kredit/Debit Selisih Tarif INA-CBG
            $lines = Posting::lines()->debit('hsp:bpjs_receivable:IDR', $packageTariffIdr);

            if ($invoice->doctor_fee_idr > 0) {
                $lines->credit('hsp:doctor_fee_revenue:IDR', $invoice->doctor_fee_idr);
            }
            if ($invoice->medication_fee_idr > 0) {
                $lines->credit('hsp:pharmacy_revenue:IDR', $invoice->medication_fee_idr);
            }

            if ($variance > 0) {
                $lines->credit('hsp:bpjs_claim_variance:IDR', $variance);
            } elseif ($variance < 0) {
                $lines->debit('hsp:bpjs_claim_variance:IDR', abs($variance));
            }

            $this->ledgerService->post(new PostingDTO(
                type: HospitalTransactionTypes::HSP_OUTPATIENT_BPJS_CLAIM->value,
                description: "Pengajuan klaim INA-CBG {$inaCbgCode} invoice {$invoice->invoice_code}",
                idempotencyKey: "HSP-CLM-{$claim->id}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'HSP_BPJS_CLAIM',
                referenceId: (string) $claim->id,
            ));

            return $claim;
        });
    }
}
