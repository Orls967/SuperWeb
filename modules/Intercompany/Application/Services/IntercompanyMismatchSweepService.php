<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Intercompany\Domain\Events\IntercompanyMismatchDetected;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyLoanSchedule;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Psr\Clock\ClockInterface;

/**
 * Layanan Penyisiran Selisih Asimetris Bilateral Antar-Entitas Grup.
 *
 * Mendeteksi pos gantung (unbilled sales), diskrepansi nilai faktur vs tagihan,
 * inkonsistensi saldo timbal balik antar-entitas, dan jadwal bunga pinjaman tidak seimbang.
 */
class IntercompanyMismatchSweepService
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    /**
     * Jalankan pemindaian seluruh diskrepansi intercompany.
     *
     * @return array{
     *     total_scanned_transactions: int,
     *     total_scanned_loans: int,
     *     mismatch_count: int,
     *     total_discrepancy_minor: int,
     *     mismatches: array<int, array{
     *         pair_key: string,
     *         type: string,
     *         transaction_code: ?string,
     *         seller_entity: string,
     *         buyer_entity: string,
     *         seller_balance_minor: int,
     *         buyer_balance_minor: int,
     *         discrepancy_minor: int,
     *         description: string
     *     }>
     * }
     */
    public function sweep(?string $period = null, int $thresholdMinor = 0): array
    {
        $now = $this->clock !== null ? CarbonImmutable::instance($this->clock->now()) : CarbonImmutable::now();
        $isoNow = $now->toIso8601String();

        $txQuery = IntercompanyTransaction::query();
        if ($period !== null) {
            $txQuery->where('created_at', 'like', "{$period}%");
        }

        $transactions = $txQuery->get();
        $loans = IntercompanyLoan::all();

        $mismatches = [];
        $totalDiscrepancyMinor = 0;

        // 1. Periksa Asimetri Faktur Penjual vs Pembeli (Floating Unbilled / Discrepancy Amount)
        foreach ($transactions as $tx) {
            $seller = $tx->selling_entity ?? ($tx->sellingEntity?->entity_code ?? 'UNKNOWN_SELLER');
            $buyer = $tx->buying_entity ?? ($tx->buyingEntity?->entity_code ?? 'UNKNOWN_BUYER');
            $amountMinor = (int) ($tx->amount_minor ?: $tx->amount_idr);
            $funcMinor = (int) ($tx->amount_functional_idr ?: $amountMinor);

            // Kondisi A: Status posted_seller tetapi purchase_bill_ref kosong (Pos Gantung Asimetris)
            if ($tx->status === 'posted_seller' && empty($tx->purchase_bill_ref)) {
                $discrepancy = $amountMinor;
                $pairKey = "{$seller}_{$buyer}";

                $mismatches[] = [
                    'pair_key' => $pairKey,
                    'type' => 'UNBILLED_POSTED_TRANSACTION',
                    'transaction_code' => $tx->transaction_code,
                    'seller_entity' => $seller,
                    'buyer_entity' => $buyer,
                    'seller_balance_minor' => $amountMinor,
                    'buyer_balance_minor' => 0,
                    'discrepancy_minor' => $discrepancy,
                    'description' => "Transaksi [{$tx->transaction_code}] diposting penjual tetapi belum memiliki purchase bill di pembeli.",
                ];

                $totalDiscrepancyMinor += $discrepancy;

                event(new IntercompanyMismatchDetected(
                    pairKey: $pairKey,
                    sellerEntityCode: $seller,
                    buyerEntityCode: $buyer,
                    sellerBalanceMinor: $amountMinor,
                    buyerBalanceMinor: 0,
                    discrepancyMinor: $discrepancy,
                    detectedAt: $isoNow
                ));

                continue;
            }

            // Kondisi B: Selisih nilai nominal transaksi vs mata uang fungsional (untuk IDR 1:1)
            if (strtoupper((string) $tx->currency) === 'IDR' && $amountMinor !== $funcMinor) {
                $diff = abs($amountMinor - $funcMinor);
                if ($diff > $thresholdMinor) {
                    $pairKey = "{$seller}_{$buyer}";
                    $mismatches[] = [
                        'pair_key' => $pairKey,
                        'type' => 'FUNCTIONAL_AMOUNT_MISMATCH',
                        'transaction_code' => $tx->transaction_code,
                        'seller_entity' => $seller,
                        'buyer_entity' => $buyer,
                        'seller_balance_minor' => $amountMinor,
                        'buyer_balance_minor' => $funcMinor,
                        'discrepancy_minor' => $diff,
                        'description' => "Transaksi [{$tx->transaction_code}] mengalami ketidaksesuaian nominal: minor={$amountMinor} vs fungsional={$funcMinor}.",
                    ];

                    $totalDiscrepancyMinor += $diff;

                    event(new IntercompanyMismatchDetected(
                        pairKey: $pairKey,
                        sellerEntityCode: $seller,
                        buyerEntityCode: $buyer,
                        sellerBalanceMinor: $amountMinor,
                        buyerBalanceMinor: $funcMinor,
                        discrepancyMinor: $diff,
                        detectedAt: $isoNow
                    ));
                }
            }
        }

        // 2. Periksa Integritas Jadwal Pinjaman Antar-Entitas
        foreach ($loans as $loan) {
            $lender = $loan->lender_entity ?? ($loan->lender?->entity_code ?? 'LENDER');
            $borrower = $loan->borrower_entity ?? ($loan->borrower?->entity_code ?? 'BORROWER');
            $loanPrincipal = (int) ($loan->principal_minor ?: $loan->principal_idr);

            $schedulesCount = $loan->schedules()->count();
            if ($schedulesCount > 0) {
                $sumPrincipal = (int) IntercompanyLoanSchedule::where('loan_id', $loan->id)->sum('principal_due_minor');
                if ($sumPrincipal !== $loanPrincipal) {
                    $diff = abs($sumPrincipal - $loanPrincipal);
                    $pairKey = "{$lender}_{$borrower}";

                    $mismatches[] = [
                        'pair_key' => $pairKey,
                        'type' => 'LOAN_SCHEDULE_MISMATCH',
                        'transaction_code' => $loan->loan_agreement_number,
                        'seller_entity' => $lender,
                        'buyer_entity' => $borrower,
                        'seller_balance_minor' => $loanPrincipal,
                        'buyer_balance_minor' => $sumPrincipal,
                        'discrepancy_minor' => $diff,
                        'description' => "Jadwal angsuran pokok pinjaman [{$loan->loan_agreement_number}] ({$sumPrincipal}) tidak sama dengan pokok pinjaman ({$loanPrincipal}).",
                    ];

                    $totalDiscrepancyMinor += $diff;

                    event(new IntercompanyMismatchDetected(
                        pairKey: $pairKey,
                        sellerEntityCode: $lender,
                        buyerEntityCode: $borrower,
                        sellerBalanceMinor: $loanPrincipal,
                        buyerBalanceMinor: $sumPrincipal,
                        discrepancyMinor: $diff,
                        detectedAt: $isoNow
                    ));
                }
            }
        }

        return [
            'total_scanned_transactions' => $transactions->count(),
            'total_scanned_loans' => $loans->count(),
            'mismatch_count' => count($mismatches),
            'total_discrepancy_minor' => $totalDiscrepancyMinor,
            'mismatches' => $mismatches,
        ];
    }
}
