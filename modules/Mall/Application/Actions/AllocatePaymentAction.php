<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Ledger\MallAccounts;
use Modules\Shared\Support\Posting;

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
    public function execute(Invoice $invoice, int $paymentAmount, string $source = 'portal', ?string $idempotencyKey = null): Invoice
    {
        if ($paymentAmount <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($invoice, $paymentAmount, $source, $idempotencyKey) {
            // Kunci baris invoice dan seluruh baris tagihannya terlebih dahulu.
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            // Semua pemeriksaan sisa tagihan dilakukan setelah baris di bawah lock.
            $remainingTotal = $lockedInvoice->remainingAmount();
            if ($paymentAmount > $remainingTotal) {
                throw new InvalidArgumentException('Nominal pembayaran (Rp '.number_format($paymentAmount).') melebihi sisa tagihan (Rp '.number_format($remainingTotal).').');
            }

            $tenant = $lockedInvoice->tenant;
            $payer = User::findOrFail($tenant->user_id);

            // Kunci juga baris tagihan agar dua pembayaran berjalan tidak dapat
            // mengalokasikan nominal yang sama ke baris yang sama secara bersamaan.
            // Urutkan baris tagihan berdasarkan prioritas pelunasan:
            // 1. Denda  2. Utilitas  3. Service Charge  4. Sewa Pokok & Bagi Hasil
            $lines = $lockedInvoice->lines()
                ->where('paid_amount', '<', DB::raw('amount'))
                ->lockForUpdate()
                ->get()
                ->sort(function ($a, $b) {
                    return $a->type->paymentPriority() <=> $b->type->paymentPriority();
                });

            // Kunci akun dompet payer agar saldo cukup dan dapat dipotong atomik.
            /** @var LedgerAccount $walletAccount */
            $walletAccount = LedgerAccount::query()
                ->where('code', 'wallet:user:'.$payer->id.':IDR')
                ->lockForUpdate()
                ->first();

            $walletAccount ??= $payer->walletAccount('IDR');
            $walletBal = BigDecimal::of($walletAccount->cached_balance ?: '0')->toInt();
            if ($walletBal < $paymentAmount) {
                throw new InvalidArgumentException('Saldo dompet tenant tidak mencukupi (Tersedia: Rp '.number_format($walletBal).', Diperlukan: Rp '.number_format($paymentAmount).').');
            }

            $key = ($idempotencyKey !== null && trim($idempotencyKey) !== '')
                ? trim($idempotencyKey)
                : (request()?->header('Idempotency-Key')
                    ?? request()?->input('idempotency_key')
                    ?? request()?->input('request_id'));

            if ($key === null || trim((string) $key) === '') {
                throw new InvalidArgumentException('Idempotency key wajib diisi untuk alokasi pembayaran invoice.');
            }

            $txKey = 'mall_pay_'.trim((string) $key);

            // Replay guard: kembalikan hasil tanpa double posting bila sudah pernah
            // tercatat dengan kunci yang sama (retry browser/gateway).
            if (LedgerTransaction::where('idempotency_key', $txKey)->exists()) {
                return $lockedInvoice->fresh(['lines', 'tenant', 'lease']);
            }

            // Urutkan baris tagihan berdasarkan prioritas pelunasan
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

            // Bangun entri double-entry ledger: Debit dompet tenant, Kredit pendapatan mall via Posting helper
            $builder = Posting::lines()->debit($walletAccount->code, $paymentAmount, 'IDR');
            foreach ($splits as $accountCode => $splitAmount) {
                if ($splitAmount > 0) {
                    $builder->credit($accountCode, $splitAmount, 'IDR');
                }
            }
            $entries = $builder->toEntries('IDR');

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
            $newInvoicePaid = $lockedInvoice->paid_amount + $paymentAmount;
            $isFullyPaid = $newInvoicePaid >= $lockedInvoice->total_amount;

            $lockedInvoice->update([
                'paid_amount' => $newInvoicePaid,
                'status' => $isFullyPaid ? InvoiceStatus::PAID : InvoiceStatus::PARTIALLY_PAID,
                'paid_at' => $isFullyPaid ? now() : $lockedInvoice->paid_at,
                'payment_reference' => $txKey,
            ]);

            // Jika lease sebelumnya disuspend dan invoice ini sudah lunas, cek apakah masih ada tunggakan lain
            $lease = $lockedInvoice->lease;
            if ($lease && $lease->status === LeaseStatus::SUSPENDED) {
                $hasOtherOverdue = Invoice::where('lease_id', $lease->id)
                    ->whereIn('status', [InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID])
                    ->where('id', '!=', $lockedInvoice->id)
                    ->exists();

                if (! $hasOtherOverdue) {
                    $lease->update(['status' => LeaseStatus::ACTIVE]);
                }
            }

            return $lockedInvoice->fresh(['lines', 'tenant', 'lease']);
        });
    }

    /**
     * Pastikan akun ledger pendapatan mall ada di sistem.
     *
     * @param  array<string>  $accountCodes
     */
    protected function ensureRevenueAccountsExist(array $accountCodes): void
    {
        foreach ($accountCodes as $code) {
            MallAccounts::get($code);
        }
    }
}
