<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Application\DTOs\CashPaymentDTO;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\OutpatientPayment;
use Modules\Hospital\Ledger\HospitalTransactionTypes;

class ProcessCashPaymentAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    public function execute(CashPaymentDTO $dto): OutpatientPayment
    {
        $cleanKey = trim($dto->idempotencyKey);
        if ($cleanKey === '') {
            throw new InvalidArgumentException('Idempotency-Key wajib disertakan.');
        }

        // Cek idempotensi: kembalikan record jika request dengan key yang sama sudah diproses
        $existing = OutpatientPayment::where('idempotency_key', $cleanKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($dto, $cleanKey) {
            $invoice = OutpatientInvoice::where('id', $dto->invoiceId)->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'PAID') {
                throw new DomainException('Tagihan ini sudah lunas.');
            }

            if ($dto->cashReceivedIdr < $invoice->total_amount_idr) {
                throw new DomainException("Nominal uang yang diserahkan (Rp {$dto->cashReceivedIdr}) kurang dari total tagihan (Rp {$invoice->total_amount_idr}).");
            }

            $changeGiven = $dto->cashReceivedIdr - $invoice->total_amount_idr;
            $paymentCode = sprintf('PAY-%s-%04d', now()->format('Ymd'), (OutpatientPayment::count() + 1));

            $payment = OutpatientPayment::create([
                'payment_code' => $paymentCode,
                'invoice_id' => $invoice->id,
                'cashier_user_id' => $dto->cashierUserId,
                'amount_idr' => $invoice->total_amount_idr,
                'cash_received_idr' => $dto->cashReceivedIdr,
                'change_given_idr' => $changeGiven,
                'payment_method' => 'CASH',
                'idempotency_key' => $cleanKey,
                'paid_at' => now(),
            ]);

            $invoice->update(['status' => 'PAID']);
            if ($invoice->encounter) {
                $invoice->encounter->update(['status' => 'SETTLED']);
            }

            // Posting double-entry pelunasan kas: Debit Kas, Kredit Piutang Pasien
            $this->ledgerService->post(new PostingDTO(
                type: HospitalTransactionTypes::HSP_OUTPATIENT_CASH_PAY->value,
                description: "Penerimaan pembayaran kas invoice {$invoice->invoice_code}",
                idempotencyKey: "HSP-PAY-{$cleanKey}",
                entries: Posting::lines()
                    ->debit('hsp:cash:IDR', $invoice->total_amount_idr)
                    ->credit('hsp:patient_receivable:IDR', $invoice->total_amount_idr)
                    ->toEntries('IDR'),
                referenceType: 'OUTPATIENT_INVOICE',
                referenceId: (string) $invoice->id,
            ));

            return $payment;
        });
    }
}
