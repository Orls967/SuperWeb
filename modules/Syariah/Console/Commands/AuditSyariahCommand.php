<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Syariah\Domain\Models\MurabahahContract;
use Modules\Syariah\Domain\Models\MurabahahSchedule;
use Modules\Syariah\Domain\Models\TazirPenalty;

/**
 * D19-5 Audit Dua Sumber Keuangan Syariah Murabahah (syb:audit):
 * 1. Σ sisa jadwal angsuran UNPAID = abs(cached_balance) piutang syb:murabahah_receivable:{akad}:IDR
 * 2. Margin diakui = Σ pengakuan margin (syb:margin_income:IDR)
 * 3. Saldo syb:deferred_margin:{akad}:IDR = margin total − margin diakui
 * 4. 0 akun bunga ('interest' atau 'bunga') pada transaksi syariah & bagan akun syb:
 * 5. Saldo syb:qardhul_hasan_fund:IDR = Σ denda ta'zir (syb_tazir_penalties)
 */
final class AuditSyariahCommand extends Command
{
    protected $signature = 'syb:audit';

    protected $description = 'Audit dua sumber Keuangan Syariah Murabahah: piutang jadwal, margin ditangguhkan/diakui, dana kebajikan ta\'zir, dan 0 akun bunga';

    public function handle(): int
    {
        $rows = [];
        $issues = 0;

        if (! Schema::hasTable('syb_murabahah_financings')) {
            $this->info('✓ syb:audit selesai (tabel belum dimigrasi).');

            return self::SUCCESS;
        }

        // 1 & 3. Periksa setiap akad ACTIVE / SETTLED terhadap saldo piutang dan margin ditangguhkan
        $contracts = MurabahahContract::whereIn('status', [
            MurabahahContract::STATUS_ACTIVE,
            MurabahahContract::STATUS_SETTLED,
        ])->orderBy('id')->get();

        $unpaidSumsByContract = MurabahahSchedule::query()
            ->where('status', MurabahahSchedule::STATUS_UNPAID)
            ->selectRaw('contract_id, COALESCE(SUM(installment_amount_idr), 0) as unpaid_total')
            ->groupBy('contract_id')
            ->pluck('unpaid_total', 'contract_id');

        $receivableCodes = [];
        $deferredCodes = [];
        foreach ($contracts as $c) {
            $receivableCodes[] = "syb:murabahah_receivable:{$c->id}:IDR";
            $deferredCodes[] = "syb:deferred_margin:{$c->id}:IDR";
        }

        $ledgerBalances = LedgerAccount::query()
            ->whereIn('code', array_merge($receivableCodes, $deferredCodes, [
                'syb:margin_income:IDR',
                'syb:qardhul_hasan_fund:IDR',
            ]))
            ->pluck('cached_balance', 'code');

        $totalRecognizedFromContracts = 0;

        foreach ($contracts as $c) {
            // Cek four-eyes DPS wajib ada pada akad ACTIVE/SETTLED
            if ($c->dps_reviewer_user_id === null || $c->dps_reviewed_at === null) {
                $rows[] = ["syb.missing_dps_review {$c->contract_code}", '1', '0', '1', 'SELISIH'];
                $issues++;
            }

            // (a) Σ sisa jadwal = saldo piutang murabahah (loan_receivable bernilai negatif di konvensi debit = -)
            $expectedUnpaid = (int) ($unpaidSumsByContract[$c->id] ?? 0);
            $recCode = "syb:murabahah_receivable:{$c->id}:IDR";
            $actualRecBalance = abs((int) ($ledgerBalances[$recCode] ?? 0));

            if ($expectedUnpaid !== $actualRecBalance) {
                $rows[] = [
                    "syb.receivable_schedule_mismatch {$c->contract_code}",
                    (string) $expectedUnpaid,
                    (string) $actualRecBalance,
                    (string) abs($expectedUnpaid - $actualRecBalance),
                    'SELISIH',
                ];
                $issues++;
            }

            // (c) Saldo deferred_margin = margin total - margin diakui
            $expectedDeferred = max(0, (int) $c->margin_amount_idr - (int) $c->recognized_margin_idr);
            $defCode = "syb:deferred_margin:{$c->id}:IDR";
            $actualDefBalance = (int) ($ledgerBalances[$defCode] ?? 0);

            if ($expectedDeferred !== $actualDefBalance) {
                $rows[] = [
                    "syb.deferred_margin_mismatch {$c->contract_code}",
                    (string) $expectedDeferred,
                    (string) $actualDefBalance,
                    (string) abs($expectedDeferred - $actualDefBalance),
                    'SELISIH',
                ];
                $issues++;
            }

            $totalRecognizedFromContracts += (int) $c->recognized_margin_idr;
        }

        // 2. (b) Total margin diakui = saldo syb:margin_income:IDR
        $actualMarginIncome = (int) ($ledgerBalances['syb:margin_income:IDR'] ?? 0);
        if ($totalRecognizedFromContracts !== $actualMarginIncome) {
            $rows[] = [
                'syb.margin_income_mismatch',
                (string) $totalRecognizedFromContracts,
                (string) $actualMarginIncome,
                (string) abs($totalRecognizedFromContracts - $actualMarginIncome),
                'SELISIH',
            ];
            $issues++;
        }

        // 4. (d) 0 akun bunga ('interest' atau 'bunga') pada akun berprefiks syb:
        $interestAccounts = LedgerAccount::query()
            ->where('code', 'like', 'syb:%')
            ->where(function ($q) {
                $q->where('code', 'like', '%interest%')
                    ->orWhere('code', 'like', '%bunga%')
                    ->orWhere('name', 'like', '%interest%')
                    ->orWhere('name', 'like', '%bunga%');
            })
            ->count();

        if ($interestAccounts > 0) {
            $rows[] = ['syb.forbidden_interest_account', '0', (string) $interestAccounts, (string) $interestAccounts, 'SELISIH'];
            $issues++;
        }

        // 5. (e) Saldo syb:qardhul_hasan_fund:IDR = Σ denda ta'zir
        $expectedTazirTotal = (int) TazirPenalty::query()->sum('amount_idr');
        $actualQardhulHasan = (int) ($ledgerBalances['syb:qardhul_hasan_fund:IDR'] ?? 0);
        if ($expectedTazirTotal !== $actualQardhulHasan) {
            $rows[] = [
                'syb.qardhul_hasan_tazir_mismatch',
                (string) $expectedTazirTotal,
                (string) $actualQardhulHasan,
                (string) abs($expectedTazirTotal - $actualQardhulHasan),
                'SELISIH',
            ];
            $issues++;
        }

        $this->table(
            ['Sumber', 'Seharusnya', 'Aktual', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows
        );

        if ($issues > 0) {
            $this->error("Ditemukan {$issues} temuan audit keuangan syariah (syb:audit).");

            return self::FAILURE;
        }

        $this->info('✓ syb:audit selesai: piutang murabahah, margin proporsional, dana Qardhul Hasan, dan 0 akun bunga seimbang.');

        return self::SUCCESS;
    }
}
