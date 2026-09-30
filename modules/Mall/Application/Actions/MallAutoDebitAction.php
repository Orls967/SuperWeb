<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Models\Invoice;

class MallAutoDebitAction
{
    public function __construct(
        protected AllocatePaymentAction $allocatePaymentAction,
    ) {}

    /**
     * Jalankan auto-debit tanpa PIN dari saldo dompet tenant untuk sebuah invoice.
     *
     * @return array{success: bool, debited: int, remaining: int, is_fully_paid: bool, message: string}
     */
    public function execute(Invoice $invoice): array
    {
        $needed = $invoice->remainingAmount();
        if ($needed <= 0) {
            return [
                'success' => true,
                'debited' => 0,
                'remaining' => 0,
                'is_fully_paid' => true,
                'message' => 'Invoice sudah lunas.',
            ];
        }

        $tenant = $invoice->tenant;
        if (! $tenant || ! $tenant->user_id) {
            return [
                'success' => false,
                'debited' => 0,
                'remaining' => $needed,
                'is_fully_paid' => false,
                'message' => 'Akun pengguna tenant tidak ditemukan.',
            ];
        }

        $payer = User::find($tenant->user_id);
        if (! $payer) {
            return [
                'success' => false,
                'debited' => 0,
                'remaining' => $needed,
                'is_fully_paid' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ];
        }

        $walletAccount = $payer->walletAccount('IDR');
        $walletBal = max(0, BigDecimal::of($walletAccount->cached_balance ?: '0')->toInt());

        if ($walletBal <= 0) {
            return [
                'success' => false,
                'debited' => 0,
                'remaining' => $needed,
                'is_fully_paid' => false,
                'message' => 'Saldo dompet tidak mencukupi untuk auto-debit (Saldo Rp 0).',
            ];
        }

        $debitAmount = min($walletBal, $needed);

        $updatedInvoice = $this->allocatePaymentAction->execute(
            $invoice,
            $debitAmount,
            source: 'auto_debit'
        );

        return [
            'success' => true,
            'debited' => $debitAmount,
            'remaining' => $updatedInvoice->remainingAmount(),
            'is_fully_paid' => $updatedInvoice->isPaid(),
            'message' => $updatedInvoice->isPaid()
                ? 'Auto-debit berhasil melunasi tagihan sebesar Rp '.number_format($debitAmount).'.'
                : 'Auto-debit berhasil memotong saldo sebesar Rp '.number_format($debitAmount).' (Sisa: Rp '.number_format($updatedInvoice->remainingAmount()).').',
        ];
    }

    /**
     * Jalankan auto-debit untuk semua invoice yang masih memiliki sisa tagihan.
     *
     * @return array{total_debited: int, processed_invoices: int}
     */
    public function autoDebitAll(?string $periodMonth = null): array
    {
        $query = Invoice::whereIn('status', [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE]);
        if ($periodMonth !== null) {
            $query->where('period_month', $periodMonth);
        }

        $invoices = $query->get();
        $totalDebited = 0;
        $processed = 0;

        foreach ($invoices as $invoice) {
            $result = $this->execute($invoice);
            if ($result['success'] && $result['debited'] > 0) {
                $totalDebited += $result['debited'];
                $processed++;
            }
        }

        return [
            'total_debited' => $totalDebited,
            'processed_invoices' => $processed,
        ];
    }
}
