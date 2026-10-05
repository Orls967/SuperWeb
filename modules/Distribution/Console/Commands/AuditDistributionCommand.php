<?php

declare(strict_types=1);

namespace Modules\Distribution\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 42.4/42.9 + 43.9 Audit: subledger AR = ledger `dist:ar:*`, rebate,
 * exposure = Σ invoice terbuka, tidak ada status konsisten rusak.
 */
class AuditDistributionCommand extends Command
{
    protected $signature = 'dist:audit';

    protected $description = 'Audit subledger AR distributor terhadap ledger (0 selisih)';

    public function handle(): int
    {
        $hasDiscrepancy = false;
        $rows = [];

        // 1. Exposure per distributor == Σ invoice terbuka.
        $exposures = DB::table('dist_distributors')->pluck('credit_exposure_idr', 'id');
        $openByDistributor = DB::table('dist_ar_invoices')
            ->whereIn('status', ['open', 'partial', 'overdue'])
            ->selectRaw('distributor_id, SUM(amount_idr + denda_idr - paid_amount_idr) as open_amt')
            ->groupBy('distributor_id')
            ->pluck('open_amt', 'distributor_id');

        foreach ($exposures as $distributorId => $exposure) {
            $expected = (int) ($openByDistributor[$distributorId] ?? 0);
            if ((int) $exposure !== $expected) {
                $hasDiscrepancy = true;
                $rows[] = [
                    'exposure:'.$distributorId, number_format($expected), number_format((int) $exposure),
                    number_format($expected - (int) $exposure), 'SELISIH',
                ];
            }
        }

        // 2. Ledger dist:ar:{id} = Σ terbuka (konvensi kredit negatif → saldo
        //    tagihan positif bila pembukuan debit-dominan; disederhanakan:
        //    selisih antara saldo akun dan exposure harus 0 atau akun belum dibuat).
        foreach ($openByDistributor as $distributorId => $openAmt) {
            $code = 'dist:ar:'.$distributorId.':IDR';
            $account = DB::table('bank_ledger_accounts')->where('code', $code)->first();
            if ($account === null) {
                continue; // akun dibuat saat posting (42.4)
            }
            $balance = (int) $account->cached_balance;
            $absOpen = abs((int) $openAmt);
            if (abs($balance) !== $absOpen && $balance !== -$absOpen) {
                $hasDiscrepancy = true;
                $rows[] = [
                    $code, number_format($absOpen), number_format($balance),
                    number_format($absOpen - abs($balance)), 'SELISIH',
                ];
            }
        }

        // 3. Invoice paid > amount = rusak.
        $overpaid = DB::table('dist_ar_invoices')
            ->whereRaw('paid_amount_idr > amount_idr + denda_idr')
            ->count();
        if ($overpaid > 0) {
            $hasDiscrepancy = true;
            $rows[] = ['ar.overpaid', '0', (string) $overpaid, (string) $overpaid, 'SELISIH'];
        }

        // 4. Tier valid (hanya bronze/silver/gold).
        $badTier = DB::table('dist_distributors')
            ->whereNotIn('tier', ['bronze', 'silver', 'gold'])->count();
        if ($badTier > 0) {
            $hasDiscrepancy = true;
            $rows[] = ['tier.invalid', '0', (string) $badTier, (string) $badTier, 'SELISIH'];
        }

        // 5. (43.9) Rebate payable ledger == Σ akrual rebate.
        $accruedTotal = (int) DB::table('dist_rebate_accruals')
            ->where('status', '!=', 'expired')->sum('rebate_amount_idr');
        $payable = DB::table('bank_ledger_accounts')
            ->where('code', 'dist:rebate_payable:IDR')
            ->value('cached_balance');
        if ($payable !== null && abs((int) $payable) !== $accruedTotal) {
            $hasDiscrepancy = true;
            $rows[] = [
                'rebate.payable', number_format($accruedTotal), number_format((int) $payable),
                number_format($accruedTotal - abs((int) $payable)), 'SELISIH',
            ];
        }

        // 6. (43.9) Konsinyasi: qty_sold_unbilled == Σ penjualan status reported.
        $stockUnbilled = (float) DB::table('dist_consignment_stocks')->sum('qty_sold_unbilled');
        $reportedQty = (float) DB::table('dist_consignment_sales')
            ->where('status', 'reported')->sum('qty');
        if (abs($stockUnbilled - $reportedQty) > 0.0001) {
            $hasDiscrepancy = true;
            $rows[] = [
                'consignment.unbilled', number_format($reportedQty, 4),
                number_format($stockUnbilled, 4), number_format($stockUnbilled - $reportedQty, 4), 'SELISIH',
            ];
        }

        // 7. (43.9) Penjualan konsinyasi terfaktur wajib menunjuk faktur.
        $invoicedNoRef = DB::table('dist_consignment_sales')
            ->where('status', 'invoiced')->whereNull('invoice_id')->count();
        if ($invoicedNoRef > 0) {
            $hasDiscrepancy = true;
            $rows[] = ['consignment.invoice_ref', '0', (string) $invoicedNoRef, (string) $invoicedNoRef, 'SELISIH'];
        }

        $this->table(['Sumber', 'Subledger', 'Ledger / eksposur', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows);

        if ($hasDiscrepancy) {
            $this->error('DITEMUKAN SELISIH PADA SUBLEDGER DISTRIBUSI.');

            return self::FAILURE;
        }

        $this->info('✓ dist:audit selesai: exposure & AR distributor sinkron (0 selisih).');

        return self::SUCCESS;
    }
}
