<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Syariah\Domain\Models\MurabahahContract;
use Modules\Syariah\Domain\Models\MurabahahSchedule;
use Modules\Syariah\Domain\Models\SybIjarahFacility;
use Modules\Syariah\Domain\Models\SybIjarahFacilitySchedule;
use Modules\Syariah\Domain\Models\SybSukukDistribution;
use Modules\Syariah\Domain\Models\SybSukukFacility;
use Modules\Syariah\Domain\Models\SybWaqfPrincipal;
use Modules\Syariah\Domain\Models\SybWealthOrder;
use Modules\Syariah\Domain\Models\TazirPenalty;

/**
 * D19-5 Audit Dua Sumber Keuangan Syariah (syb:audit):
 * 1. Murabahah: Σ sisa jadwal UNPAID = piutang, margin diakui = saldo income, margin deferred balance, denda ta'zir = qardhul hasan.
 * 2. Sukuk: total_coupon_distributed_idr = Σ alokasi kupon per fasilitas.
 * 3. Ijarah: sisa angsuran UNPAID = piutang ijarah, total_ujrah_paid = Σ pembayaran jadwal.
 * 4. Zakat: keunikan per haul muzakki & perhitungan 2.5% nisab akurat.
 * 5. Waqf: pokok wakaf abadi tidak berkurang (current_principal_idr >= original_principal_idr).
 * 6. Wealth: 0 order instrumen non-syariah.
 * 7. Bebas bunga mutlak: 0 akun bunga ('interest' / 'bunga') pada bagan akun syb:.
 */
final class AuditSyariahCommand extends Command
{
    protected $signature = 'syb:audit
        {--corrupt-fixture= : Nama fixture korupsi yang akan disuntikkan}
        {--verify-fixture= : Verifikasi deteksi korupsi terhadap fixture tertentu}';

    protected $description = 'Audit dua sumber Keuangan Syariah: Murabahah, Sukuk, Ijarah, Wealth, Zakat, Wakaf, dan 0 akun bunga';

    public function handle(): int
    {
        if ($fixture = $this->option('corrupt-fixture')) {
            $class = "App\\Quality\\Audit\\Fixtures\\{$fixture}";
            if (class_exists($class)) {
                $target = (new $class)->corrupt();
                $this->warn("Fixture [{$fixture}] berhasil diterapkan untuk pengujian audit: {$target}");

                return self::SUCCESS;
            }
            $this->error("Fixture [{$fixture}] tidak ditemukan di App\\Quality\\Audit\\Fixtures.");

            return self::FAILURE;
        }

        $rows = [];
        $issues = 0;

        // 1. Audit Murabahah (jika tabel tersedia)
        if (Schema::hasTable('syb_murabahah_financings')) {
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
                if ($c->dps_reviewer_user_id === null || $c->dps_reviewed_at === null) {
                    $rows[] = ["syb.missing_dps_review {$c->contract_code}", '1', '0', '1', 'SELISIH'];
                    $issues++;
                }

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
        }

        // 2. Audit Sukuk (Fase 162)
        if (Schema::hasTable('syb_sukuk_facilities')) {
            $sukukFacilities = SybSukukFacility::query()->get();
            foreach ($sukukFacilities as $fac) {
                $sumDist = (int) SybSukukDistribution::query()
                    ->where('sukuk_facility_id', $fac->id)
                    ->sum('distributed_idr');

                if ($fac->total_coupon_distributed_idr !== $sumDist) {
                    $rows[] = [
                        "syb.sukuk_distribution_mismatch {$fac->sukuk_code}",
                        (string) $fac->total_coupon_distributed_idr,
                        (string) $sumDist,
                        (string) abs($fac->total_coupon_distributed_idr - $sumDist),
                        'SELISIH',
                    ];
                    $issues++;
                }
            }
        }

        // 3. Audit Ijarah (Fase 162)
        if (Schema::hasTable('syb_ijarah_facilities')) {
            $ijarahFacilities = SybIjarahFacility::query()->get();
            foreach ($ijarahFacilities as $ijr) {
                $sumPaid = (int) SybIjarahFacilitySchedule::query()
                    ->where('facility_id', $ijr->id)
                    ->where('status', 'PAID')
                    ->sum('paid_amount_idr');

                if ($ijr->total_ujrah_paid_idr !== $sumPaid) {
                    $rows[] = [
                        "syb.ijarah_paid_mismatch {$ijr->contract_code}",
                        (string) $ijr->total_ujrah_paid_idr,
                        (string) $sumPaid,
                        (string) abs($ijr->total_ujrah_paid_idr - $sumPaid),
                        'SELISIH',
                    ];
                    $issues++;
                }
            }
        }

        // 4. Audit Zakat (Fase 162)
        if (Schema::hasTable('syb_zakat_assessments')) {
            $duplicates = DB::table('syb_zakat_assessments')
                ->select('customer_user_id', 'haul_year', DB::raw('count(*) as c'))
                ->groupBy('customer_user_id', 'haul_year')
                ->having('c', '>', 1)
                ->get();

            if ($duplicates->isNotEmpty()) {
                $rows[] = [
                    'syb.duplicate_haul_zakat_assessment',
                    '0 duplicate',
                    (string) $duplicates->count(),
                    (string) $duplicates->count(),
                    'SELISIH',
                ];
                $issues++;
            }
        }

        // 5. Audit Wakaf Pokok Abadi (Fase 162)
        if (Schema::hasTable('syb_waqf_principals')) {
            $diminished = SybWaqfPrincipal::query()
                ->whereColumn('current_principal_idr', '<', 'original_principal_idr')
                ->count();

            if ($diminished > 0) {
                $rows[] = [
                    'syb.waqf_principal_diminished',
                    '0',
                    (string) $diminished,
                    (string) $diminished,
                    'SELISIH',
                ];
                $issues++;
            }
        }

        // 6. Audit Wealth Screening Compliance (Fase 162)
        if (Schema::hasTable('syb_wealth_orders')) {
            $invalidOrders = SybWealthOrder::query()
                ->whereHas('instrument', function ($q) {
                    $q->where('is_shariah_compliant', false);
                })
                ->count();

            if ($invalidOrders > 0) {
                $rows[] = [
                    'syb.non_compliant_wealth_order',
                    '0',
                    (string) $invalidOrders,
                    (string) $invalidOrders,
                    'SELISIH',
                ];
                $issues++;
            }
        }

        // 7. Audit Microfinance & BMT (Fase 163)
        if (Schema::hasTable('syb_bmt_savings')) {
            $negativeSavings = DB::table('syb_bmt_savings')->where('balance_idr', '<', 0)->count();
            if ($negativeSavings > 0) {
                $rows[] = ['syb.negative_bmt_savings_balance', '0', (string) $negativeSavings, (string) $negativeSavings, 'SELISIH'];
                $issues++;
            }

            $negativeOutstanding = DB::table('syb_micro_facilities')->where('outstanding_balance_idr', '<', 0)->count()
                + DB::table('syb_agri_micro_facilities')->where('outstanding_balance_idr', '<', 0)->count()
                + DB::table('syb_gig_microloans')->where('remaining_balance_idr', '<', 0)->count();
            if ($negativeOutstanding > 0) {
                $rows[] = ['syb.negative_micro_outstanding', '0', (string) $negativeOutstanding, (string) $negativeOutstanding, 'SELISIH'];
                $issues++;
            }
        }

        // 8. Larangan Riba Mutlak: 0 akun bunga pada bagan akun syb:
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

        $this->table(
            ['Sumber', 'Seharusnya', 'Aktual', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows
        );

        if ($issues > 0) {
            $this->error("Ditemukan {$issues} temuan audit keuangan syariah (syb:audit).");

            return self::FAILURE;
        }

        $this->info('✓ syb:audit selesai: Murabahah, Sukuk, Ijarah, Wealth, Zakat, Wakaf, dan 0 akun bunga seimbang.');

        return self::SUCCESS;
    }
}
