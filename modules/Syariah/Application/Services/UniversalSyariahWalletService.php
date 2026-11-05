<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Syariah\Contracts\SyariahPaymentResult;
use Modules\Syariah\Contracts\SyariahWalletPayment;
use Modules\Syariah\Domain\Models\SybUniversalWalletTransaction;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;
use RuntimeException;

final class UniversalSyariahWalletService implements SyariahWalletPayment
{
    public function __construct(
        private readonly HalalTransactionFilterService $filterService,
        private readonly ShariahLedgerPoster $ledgerPoster,
    ) {}

    public function verifyHalalStatus(string $merchantCode, string $businessLineCode): bool
    {
        return $this->filterService->isMerchantCompliant($merchantCode, $businessLineCode);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function pay(
        string $customerAccountRef,
        string $merchantCode,
        string $businessLineCode,
        int $amountMinor,
        string $orderReference,
        array $metadata = []
    ): SyariahPaymentResult {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        // Idempotency guard: cek apakah transaksi untuk orderReference ini sudah pernah sukses
        $existing = SybUniversalWalletTransaction::where('order_reference', $orderReference)
            ->where('status', 'SUCCESS')
            ->first();

        if ($existing) {
            return new SyariahPaymentResult(
                success: true,
                transactionRef: $existing->transaction_ref,
                orderReference: $existing->order_reference,
                customerAccountRef: $existing->customer_account_ref,
                merchantCode: $existing->merchant_code,
                businessLineCode: $existing->business_line_code,
                grossAmountMinor: $existing->gross_amount_minor,
                adminFeeMinor: $existing->admin_fee_minor,
                netMerchantAmountMinor: $existing->net_merchant_amount_minor,
                isHalalVerified: $existing->is_halal_verified,
                ledgerJournalRef: $existing->ledger_journal_ref,
                metadata: $existing->metadata ?? [],
            );
        }

        // Jalankan halal compliance filter gate
        try {
            $this->filterService->validateMerchantForPayment($merchantCode, $businessLineCode);
        } catch (\Throwable $e) {
            // Catat penolakan transaksi di database
            $rejectionRef = 'TX-REJ-'.hash('sha256', $orderReference.microtime());
            SybUniversalWalletTransaction::create([
                'transaction_ref' => substr($rejectionRef, 0, 32),
                'order_reference' => $orderReference,
                'customer_account_ref' => $customerAccountRef,
                'merchant_code' => $merchantCode,
                'business_line_code' => $businessLineCode,
                'gross_amount_minor' => $amountMinor,
                'admin_fee_minor' => 0,
                'net_merchant_amount_minor' => 0,
                'is_halal_verified' => false,
                'ledger_journal_ref' => null,
                'status' => 'REJECTED',
                'rejection_reason' => $e->getMessage(),
                'metadata' => $metadata,
            ]);

            throw $e;
        }

        // Biaya ujrah layanan transaksi syariah (misal: 100 bps / 1% dengan cap Rp 5.000)
        $adminFee = min(500_000, (int) intval(round(($amountMinor * 100) / 10000.0)));
        $netMerchant = $amountMinor - $adminFee;

        $txRef = 'TX-SYB-'.hash('sha256', $orderReference.':'.$customerAccountRef);
        $txRef = substr($txRef, 0, 32);

        return DB::transaction(function () use (
            $txRef,
            $orderReference,
            $customerAccountRef,
            $merchantCode,
            $businessLineCode,
            $amountMinor,
            $adminFee,
            $netMerchant,
            $metadata
        ) {
            SyariahAccounts::ensureAccountsExist();

            // Posting Ledger ganda
            // Debit: syb:wallet_float:IDR (pengurangan saldo simpanan dompet syariah)
            // Kredit: syb:merchant_payable:IDR (kewajiban bayar ke merchant)
            // Kredit: syb:wallet_ujrah_income:IDR (pendapatan fee admin platform)
            $entries = [
                [
                    'account_code' => 'syb:wallet_float:IDR',
                    'amount' => -$amountMinor,
                ],
                [
                    'account_code' => 'syb:merchant_payable:IDR',
                    'amount' => $netMerchant,
                ],
            ];

            if ($adminFee > 0) {
                $entries[] = [
                    'account_code' => 'syb:wallet_ujrah_income:IDR',
                    'amount' => $adminFee,
                ];
            }

            $journalRef = 'syb:post:'.hash('sha256', SyariahTransactionTypes::SYB_WALLET_PAYMENT->value.':'.$orderReference);

            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_WALLET_PAYMENT,
                reference: $orderReference,
                description: "Pembayaran dompet syariah 30 lini [{$businessLineCode}] untuk pesanan {$orderReference}",
                entries: $entries
            );

            $tx = SybUniversalWalletTransaction::create([
                'transaction_ref' => $txRef,
                'order_reference' => $orderReference,
                'customer_account_ref' => $customerAccountRef,
                'merchant_code' => $merchantCode,
                'business_line_code' => $businessLineCode,
                'gross_amount_minor' => $amountMinor,
                'admin_fee_minor' => $adminFee,
                'net_merchant_amount_minor' => $netMerchant,
                'is_halal_verified' => true,
                'ledger_journal_ref' => $journalRef,
                'status' => 'SUCCESS',
                'rejection_reason' => null,
                'metadata' => $metadata,
            ]);

            return new SyariahPaymentResult(
                success: true,
                transactionRef: $tx->transaction_ref,
                orderReference: $tx->order_reference,
                customerAccountRef: $tx->customer_account_ref,
                merchantCode: $tx->merchant_code,
                businessLineCode: $tx->business_line_code,
                grossAmountMinor: $tx->gross_amount_minor,
                adminFeeMinor: $tx->admin_fee_minor,
                netMerchantAmountMinor: $tx->net_merchant_amount_minor,
                isHalalVerified: true,
                ledgerJournalRef: $journalRef,
                metadata: $metadata,
            );
        });
    }
}
