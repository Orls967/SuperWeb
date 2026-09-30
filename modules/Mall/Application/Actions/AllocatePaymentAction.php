<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Models\Invoice;

class AllocatePaymentAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    /**
     * Alokasikan pembayaran invoice sesuai prioritas:
     * 1. Denda
     * 2. Utilitas (Lembur AC, Air, Listrik)
     * 3. Service Charge
     * 4. Sewa Pokok & Bagi Hasil
     *
     * @throws InvalidArgumentException
     */
    public function execute(Invoice $invoice, int $paymentAmount, string $source = 'portal'): Invoice
    {
        if ($paymentAmount <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih besar dari 0.');
        }

        $remainingTotal = $invoice->remainingAmount();
        if ($paymentAmount > $remainingTotal) {
            throw new InvalidArgumentException('Nominal pembayaran (Rp '.number_format($paymentAmount).') melebihi sisa tagihan (Rp '.number_format($remainingTotal).').');
        }

        $tenant = $invoice->tenant;
        $payer = User::findOrFail($tenant->user_id);
        $walletAccount = $payer->walletAccount('IDR');

        // Validasi saldo dompet
        $walletBal = BigDecimal::of($walletAccount->cached_balance ?: '0')->toInt();
        if ($walletBal < $paymentAmount) {
            throw new InvalidArgumentException('Saldo dompet tenant tidak mencukupi (Tersedia: Rp '.number_format($walletBal).', Diperlukan: Rp '.number_format($paymentAmount).').');
        }

        return DB::transaction(function () use ($invoice, $paymentAmount, $payer, $walletAccount, $source) {
            // Urutkan baris tagihan berdasarkan prioritas pelunasan
            $lines = $invoice->lines()
                ->where('paid_amount', '<', DB::raw('amount'))
                ->get()
                ->sort(function ($a, $b) {
                    return $a->type->paymentPriority() <=> $b->type->paymentPriority();
                });

            $remainingToAllocate = $paymentAmount;
            $splits = []; // [accountCode => amount]

            foreach ($lines as $line) {
                if ($remainingToAllocate <= 0) {
                    break;
                }

                $needed = $line->remainingAmount();
                $allocated = min($remainingToAllocate, $needed);

                $newPaid = $line->paid_amount + $allocated;
                $line->update([
                    'paid_amount' => $newPaid,
                    'status' => $newPaid >= $line->amount ? 'paid' : 'partially_paid',
                ]);

                $revCode = $line->type->ledgerAccountCode();
                $splits[$revCode] = ($splits[$revCode] ?? 0) + $allocated;

                $remainingToAllocate -= $allocated;
            }

            // Pastikan seluruh akun pendapatan yang terlibat sudah terdaftar di ledger
            $this->ensureRevenueAccountsExist(array_keys($splits));

            // Bangun entri double-entry ledger: Debit dompet tenant, Kredit pendapatan mall
            $entries = [
                PostingEntryDTO::forAccount($walletAccount->id, 'IDR', BigDecimal::of($paymentAmount)->negated()),
            ];

            foreach ($splits as $accountCode => $splitAmount) {
                if ($splitAmount > 0) {
                    $entries[] = PostingEntryDTO::forCode($accountCode, 'IDR', BigDecimal::of($splitAmount));
                }
            }

            $txKey = 'mall_pay_'.$invoice->id.'_'.Str::random(12);
            $dto = new PostingDTO(
                type: TransactionType::LEASE_BILLING->value,
                description: "Pembayaran Tagihan Mall {$invoice->invoice_number} ({$source})",
                idempotencyKey: $txKey,
                entries: $entries,
                referenceType: Invoice::class,
                referenceId: $invoice->id,
                meta: [
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $invoice->tenant_id,
                    'amount' => (string) $paymentAmount,
                    'splits' => $splits,
                    'source' => $source,
                ],
                createdBy: $payer->id,
                postedAt: now(),
            );

            $this->ledger->post($dto);

            // Update status dan nominal terbayar invoice
            $newInvoicePaid = $invoice->paid_amount + $paymentAmount;
            $isFullyPaid = $newInvoicePaid >= $invoice->total_amount;

            $invoice->update([
                'paid_amount' => $newInvoicePaid,
                'status' => $isFullyPaid ? InvoiceStatus::PAID : InvoiceStatus::PARTIALLY_PAID,
                'paid_at' => $isFullyPaid ? now() : $invoice->paid_at,
                'payment_reference' => $txKey,
            ]);

            // Jika lease sebelumnya disuspend dan invoice ini sudah lunas, cek apakah masih ada tunggakan lain
            $lease = $invoice->lease;
            if ($lease && $lease->status === LeaseStatus::SUSPENDED) {
                $hasOtherOverdue = Invoice::where('lease_id', $lease->id)
                    ->whereIn('status', [InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID])
                    ->where('id', '!=', $invoice->id)
                    ->exists();

                if (! $hasOtherOverdue) {
                    $lease->update(['status' => LeaseStatus::ACTIVE]);
                }
            }

            return $invoice->fresh(['lines', 'tenant', 'lease']);
        });
    }

    /**
     * Pastikan akun ledger pendapatan mall ada di sistem.
     *
     * @param  array<string>  $accountCodes
     */
    protected function ensureRevenueAccountsExist(array $accountCodes): void
    {
        $accountNames = [
            'revenue:mall:rent:IDR' => 'Pendapatan Sewa & Bagi Hasil Mall',
            'revenue:mall:service_charge:IDR' => 'Pendapatan Service Charge Mall',
            'revenue:mall:utilities:electricity:IDR' => 'Pendapatan Utilitas Listrik Mall',
            'revenue:mall:utilities:water:IDR' => 'Pendapatan Utilitas Air Bersih Mall',
            'revenue:mall:utilities:ac_overtime:IDR' => 'Pendapatan Lembur AC Mall',
            'revenue:mall:parking:IDR' => 'Pendapatan Parkir Mall',
            'revenue:mall:repairs:IDR' => 'Pendapatan Perbaikan Fasilitas Mall',
            'revenue:mall:penalties:IDR' => 'Pendapatan Denda Keterlambatan Mall',
        ];

        foreach ($accountCodes as $code) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $accountNames[$code] ?? 'Pendapatan Operasional Mall',
                    'asset_code' => 'IDR',
                    'kind' => AccountKind::REVENUE,
                    'allow_negative' => false,
                ]
            );
        }
    }
}
