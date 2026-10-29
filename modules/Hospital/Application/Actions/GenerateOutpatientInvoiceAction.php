<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\TariffVersion;
use Modules\Hospital\Ledger\HospitalTransactionTypes;

class GenerateOutpatientInvoiceAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    public function execute(OutpatientEncounter $encounter): OutpatientInvoice
    {
        return DB::transaction(function () use ($encounter) {
            $today = now()->toDateString();
            $tariff = TariffVersion::where('service_code', 'SRV-DOC-SPECIALIST')
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $today)
                ->orderBy('effective_from', 'desc')
                ->first();

            $doctorFee = $tariff !== null ? (int) $tariff->amount_idr : 150000;
            $medicationFee = (int) $encounter->prescriptions()->sum('subtotal_idr');
            $totalAmount = $doctorFee + $medicationFee;

            $invoiceCode = sprintf('INV-%s-%04d', now()->format('Ymd'), (OutpatientInvoice::count() + 1));

            $invoice = OutpatientInvoice::create([
                'invoice_code' => $invoiceCode,
                'encounter_id' => $encounter->id,
                'doctor_fee_idr' => $doctorFee,
                'medication_fee_idr' => $medicationFee,
                'total_amount_idr' => $totalAmount,
                'payment_method' => $encounter->payment_method,
                'status' => 'PENDING',
            ]);

            $encounter->update(['status' => 'INVOICED']);

            // Post initial billing to ledger for CASH patients
            if ($encounter->payment_method === 'CASH' && $totalAmount > 0) {
                $lines = Posting::lines()->debit('hsp:patient_receivable:IDR', $totalAmount);
                if ($doctorFee > 0) {
                    $lines->credit('hsp:doctor_fee_revenue:IDR', $doctorFee);
                }
                if ($medicationFee > 0) {
                    $lines->credit('hsp:pharmacy_revenue:IDR', $medicationFee);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: HospitalTransactionTypes::HSP_OUTPATIENT_BILLING->value,
                    description: "Penagihan rawat jalan umum invoice {$invoice->invoice_code}",
                    idempotencyKey: "HSP-BILL-{$invoice->id}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'OUTPATIENT_INVOICE',
                    referenceId: (string) $invoice->id,
                ));
            }

            return $invoice;
        });
    }
}
