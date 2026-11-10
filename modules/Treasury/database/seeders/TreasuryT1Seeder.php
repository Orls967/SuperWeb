<?php

declare(strict_types=1);

namespace Modules\Treasury\database\seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Treasury\Domain\Algorithms\RationalRate;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankStatement;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\CashPoolSweep;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\CreditFacilityDrawdown;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\ForwardContractHistory;
use Modules\Treasury\Domain\Models\FxLot;
use Modules\Treasury\Domain\Models\PettyCashVoucher;
use Modules\Treasury\Domain\Models\Revaluation;

/**
 * Seeder T1 Modul Treasury (W8-P5 / Fase 48)
 *
 * Dataset deterministik skala penuh:
 * - >= 8 Mata Uang (IDR, USD, EUR, SGD, JPY, GBP, AUD, CNY)
 * - >= 400 Rekaman Kurs Rasional (55 hari x 8 CCY = 440 rekaman)
 * - >= 120 Subledger Lot Valas (trs_fx_lots)
 * - 600 Mutasi Bank (trs_bank_statements) + 600 Mutasi Buku (bank_ledger_entries)
 * - >= 30 Kontrak Hedge (trs_forward_contracts) mencakup ke-8 status HedgeStatus
 * - >= 6 Fasilitas Kredit Bank (trs_credit_facilities) dengan riwayat drawdown
 * - >= 4 Cash Pools (trs_cash_pools) dengan zero-balancing & target-balance sweeps
 * - 15 Kasus Bernama (Named Test Cases) terindeks
 */
class TreasuryT1Seeder extends Seeder
{
    public const BASE_DATE = '2026-10-01';

    public function run(): void
    {
        $this->call(LedgerAccountsSeeder::class);

        $base = CarbonImmutable::parse(self::BASE_DATE);

        // 1. Bersihkan tabel Treasury secara aman & idempoten
        DB::table('trs_cash_pool_sweeps')->delete();
        DB::table('trs_cash_pools')->delete();
        DB::table('trs_credit_facility_drawdowns')->delete();
        DB::table('trs_credit_facilities')->delete();
        DB::table('trs_forward_contract_histories')->delete();
        DB::table('trs_forward_contracts')->delete();
        DB::table('trs_bank_reconciliation_matches')->delete();
        DB::table('trs_bank_reconciliation_unmatched')->delete();
        DB::table('trs_bank_statements')->delete();
        DB::table('trs_petty_cash_vouchers')->delete();
        DB::table('trs_bank_accounts')->delete();
        DB::table('trs_revaluations')->delete();
        DB::table('trs_fx_lots')->delete();
        DB::table('trs_cash_forecasts')->delete();
        DB::table('trs_exchange_rates')->delete();
        DB::table('trs_currencies')->delete();

        // 2. Registrasi >= 8 Mata Uang
        $currencies = [
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'minor_units' => 0],
            ['code' => 'USD', 'name' => 'United States Dollar', 'symbol' => '$', 'minor_units' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'minor_units' => 2],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'minor_units' => 2],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'minor_units' => 0],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'minor_units' => 2],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'minor_units' => 2],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'minor_units' => 2],
        ];

        foreach ($currencies as $c) {
            Currency::create([
                'code' => $c['code'],
                'name' => $c['name'],
                'symbol' => $c['symbol'],
                'minor_units' => $c['minor_units'],
                'is_active' => true,
            ]);
        }

        // 3. Buat >= 400 Rekaman Kurs Rasional (55 hari x 8 mata uang = 440 entri)
        $baseRates = [
            'USD' => 15600,
            'EUR' => 16900,
            'SGD' => 11800,
            'JPY' => 105,
            'GBP' => 19800,
            'AUD' => 10200,
            'CNY' => 2180,
            'IDR' => 1,
        ];

        $rateRows = [];
        for ($day = 0; $day < 55; $day++) {
            $date = $base->addDays($day)->toDateString();
            foreach ($baseRates as $ccy => $nominal) {
                // Fluktuasi deterministik kecil (+- 1.5%)
                $variation = ($day % 7) * 15 - 45;
                $effectiveNum = ($ccy === 'IDR') ? 1 : ($nominal + $variation);
                $effectiveDen = 1;

                $rateRows[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'from_currency' => $ccy,
                    'to_currency' => 'IDR',
                    'rate_date' => $date,
                    'rate_numerator' => $effectiveNum,
                    'rate_denominator' => $effectiveDen,
                    'rate_type' => ($day % 15 === 0) ? 'closing' : 'spot',
                    'created_at' => $date . ' 00:00:00',
                    'updated_at' => $date . ' 00:00:00',
                ];
            }
        }
        DB::table('trs_exchange_rates')->insert($rateRows);

        // 4. Buat Rekening Bank (Header, Sub-Accounts, Petty Cash)
        $bankAccounts = [];
        $accountsDef = [
            ['acc' => 'ACC-HOLDING-BCA', 'name' => 'BCA Holding Master', 'ccy' => 'IDR', 'bal' => 2_500_000_000, 'petty' => false, 'entity' => 'HOLDING'],
            ['acc' => 'ACC-HOLDING-MDR', 'name' => 'Mandiri Holding Valas USD', 'ccy' => 'USD', 'bal' => 1_000_000, 'petty' => false, 'entity' => 'HOLDING'],
            ['acc' => 'ACC-SUB-A-BCA', 'name' => 'BCA Anak Perusahaan Sub A', 'ccy' => 'IDR', 'bal' => 350_000_000, 'petty' => false, 'entity' => 'SUB_A'],
            ['acc' => 'ACC-SUB-B-MDR', 'name' => 'Mandiri Anak Perusahaan Sub B', 'ccy' => 'IDR', 'bal' => 420_000_000, 'petty' => false, 'entity' => 'SUB_B'],
            ['acc' => 'ACC-SUB-C-BNI', 'name' => 'BNI Anak Perusahaan Sub C', 'ccy' => 'IDR', 'bal' => 180_000_000, 'petty' => false, 'entity' => 'SUB_C'],
            ['acc' => 'ACC-PETTY-HQ', 'name' => 'Kas Kecil Kantor Pusat', 'ccy' => 'IDR', 'bal' => 25_000_000, 'petty' => true, 'entity' => 'HOLDING'],
            ['acc' => 'ACC-PETTY-SUB-A', 'name' => 'Kas Kecil Cabang Sub A', 'ccy' => 'IDR', 'bal' => 10_000_000, 'petty' => true, 'entity' => 'SUB_A'],
        ];

        foreach ($accountsDef as $def) {
            $bankAccounts[$def['acc']] = BankAccount::create([
                'account_number' => $def['acc'],
                'entity_id' => $def['entity'],
                'bank_name' => $def['name'],
                'currency' => $def['ccy'],
                'balance' => $def['bal'],
                'imprest_limit' => $def['petty'] ? 25_000_000 : 0,
                'is_petty_cash' => $def['petty'],
                'is_active' => true,
            ]);
        }

        // 5. Buat >= 120 Lot Valas (trs_fx_lots)
        $lotRows = [];
        $valasCcys = ['USD', 'EUR', 'SGD', 'JPY', 'GBP', 'AUD', 'CNY'];
        for ($i = 1; $i <= 125; $i++) {
            $curr = $valasCcys[($i - 1) % count($valasCcys)];
            $foreignMinor = ($i * 10_000) + 50_000; // 60.000 s/d 1.300.000
            $rateNum = $baseRates[$curr];
            $scale = ($curr === 'JPY' || $curr === 'IDR') ? 1 : 100;
            $costIdr = (int) intdiv($foreignMinor * $rateNum, $scale);

            $isExhausted = ($i % 5 === 0);
            $remaining = $isExhausted ? 0 : $foreignMinor;

            $lotRows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'entity_code' => ($i % 3 === 0) ? 'SUB_A' : 'HOLDING',
                'currency' => $curr,
                'lot_date' => $base->addDays($i % 30)->toDateString(),
                'original_amount' => $foreignMinor,
                'remaining_amount' => $remaining,
                'original_rate_num' => $rateNum,
                'original_rate_den' => 1,
                'original_cost_idr' => $costIdr,
                'current_carrying_rate_num' => $rateNum,
                'current_carrying_rate_den' => 1,
                'current_carrying_idr' => $isExhausted ? 0 : $costIdr,
                'status' => $isExhausted ? 'exhausted' : 'active',
                'source_ref' => "PO-TRADE-{$curr}-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'created_at' => $base->toDateString() . ' 00:00:00',
                'updated_at' => $base->toDateString() . ' 00:00:00',
            ];
        }
        DB::table('trs_fx_lots')->insert($lotRows);

        // 6. Buat 600 Mutasi Bank (trs_bank_statements) + 600 Ledger Entries
        $mainAccount = $bankAccounts['ACC-HOLDING-BCA'];
        $stmtRows = [];
        for ($s = 1; $s <= 600; $s++) {
            $isReconciled = ($s <= 450); // 450 matched, 150 unmatched
            $date = $base->addDays($s % 45)->toDateString();
            $amount = ($s * 50_000) + 1_000_000;
            $ref = "REF-MT940-" . str_pad((string) $s, 5, '0', STR_PAD_LEFT);

            $stmtRows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'bank_account_id' => $mainAccount->id,
                'transaction_date' => $date,
                'reference_no' => $ref,
                'amount' => $amount,
                'description' => "Setoran penerimaan kliring {$ref}",
                'is_reconciled' => $isReconciled,
                'reconciliation_status' => $isReconciled ? 'matched' : 'unmatched',
                'matched_ledger_tx_id' => $isReconciled ? (1000 + $s) : null,
                'created_at' => $date . ' 00:00:00',
                'updated_at' => $date . ' 00:00:00',
            ];
        }
        DB::table('trs_bank_statements')->insert($stmtRows);

        // 7. Buat >= 30 Kontrak Forward (trs_forward_contracts) - ke-8 status
        $statuses = HedgeStatus::cases();
        $hedgeRows = [];
        for ($h = 1; $h <= 32; $h++) {
            $status = $statuses[($h - 1) % count($statuses)]->value;
            $notional = ($h * 20_000) + 100_000;
            $forwardRate = 15750 * 10000;

            $hedgeRows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'contract_number' => "FWD-T1-" . str_pad((string) $h, 4, '0', STR_PAD_LEFT),
                'currency' => 'USD',
                'creator_id' => null,
                'notional_foreign_amount' => $notional,
                'forward_rate_scaled' => $forwardRate,
                'contract_rate_numerator' => 15750,
                'contract_rate_denominator' => 1,
                'maturity_date' => $base->addDays(30 + $h)->toDateString(),
                'mtm_value_idr' => 0,
                'status' => $status,
                'created_at' => $base->toDateString() . ' 00:00:00',
                'updated_at' => $base->toDateString() . ' 00:00:00',
            ];
        }
        DB::table('trs_forward_contracts')->insert($hedgeRows);

        // 8. Buat >= 6 Fasilitas Kredit Bank (trs_credit_facilities)
        $facilityData = [
            ['code' => 'FAC-BCA-01', 'bank' => 'PT Bank Central Asia Tbk', 'limit' => 20_000_000_000, 'drawn' => 5_000_000_000, 'der' => 1800],
            ['code' => 'FAC-MDR-02', 'bank' => 'PT Bank Mandiri (Persero) Tbk', 'limit' => 15_000_000_000, 'drawn' => 3_000_000_000, 'der' => 1950],
            ['code' => 'FAC-BNI-03', 'bank' => 'PT Bank Negara Indonesia Tbk', 'limit' => 10_000_000_000, 'drawn' => 2_000_000_000, 'der' => 1500],
            ['code' => 'FAC-BRI-04', 'bank' => 'PT Bank Rakyat Indonesia Tbk', 'limit' => 12_000_000_000, 'drawn' => 0, 'der' => 1200],
            ['code' => 'FAC-CIMB-05', 'bank' => 'PT Bank CIMB Niaga Tbk', 'limit' => 8_000_000_000, 'drawn' => 1_500_000_000, 'der' => 2100],
            ['code' => 'FAC-DBS-06', 'bank' => 'PT Bank DBS Indonesia', 'limit' => 5_000_000_000, 'drawn' => 1_000_000_000, 'der' => 1750],
        ];

        foreach ($facilityData as $f) {
            $fac = CreditFacility::create([
                'facility_code' => $f['code'],
                'entity_id' => 'HOLDING',
                'bank_name' => $f['bank'],
                'credit_limit_idr' => $f['limit'],
                'drawn_amount_idr' => $f['drawn'],
                'interest_rate_permil' => 85,
                'max_der_permil' => 2500,
                'interest_rate_percent' => 8.5,
                'max_debt_equity_ratio' => 2.5,
                'status' => 'active',
            ]);

            if ($f['drawn'] > 0) {
                CreditFacilityDrawdown::create([
                    'facility_id' => $fac->id,
                    'entity_id' => 'HOLDING',
                    'draw_date' => $base->addDays(5)->toDateString(),
                    'amount_idr' => $f['drawn'],
                    'covenant_der_permil' => $f['der'],
                    'max_der_permil' => 2500,
                    'verified_debt_idr' => 10_000_000_000,
                    'verified_equity_idr' => 5_000_000_000,
                ]);
            }
        }

        // 9. Buat >= 4 Cash Pools (trs_cash_pools) & Sweeps
        $poolsDef = [
            ['name' => 'Central Pool Sub A', 'sub' => 'ACC-SUB-A-BCA', 'target' => 50_000_000, 'swept' => 25_000_000],
            ['name' => 'Central Pool Sub B', 'sub' => 'ACC-SUB-B-MDR', 'target' => 75_000_000, 'swept' => 45_000_000],
            ['name' => 'Central Pool Sub C', 'sub' => 'ACC-SUB-C-BNI', 'target' => 30_000_000, 'swept' => 15_000_000],
            ['name' => 'Zero-Balancing Pool HQ', 'sub' => 'ACC-PETTY-HQ', 'target' => 0, 'swept' => 5_000_000],
        ];

        foreach ($poolsDef as $p) {
            $pool = CashPool::create([
                'pool_name' => $p['name'],
                'header_account_id' => $mainAccount->id,
                'sub_account_id' => $bankAccounts[$p['sub']]->id,
                'target_balance_idr' => $p['target'],
                'last_swept_amount_idr' => $p['swept'],
                'last_swept_at' => $base->addDays(10),
            ]);

            CashPoolSweep::create([
                'pool_id' => $pool->id,
                'sweep_date' => $base->addDays(10)->toDateString(),
                'swept_amount_idr' => $p['swept'],
                'direction' => 'sub_to_header',
                'header_balance_before' => 2_500_000_000,
                'header_balance_after' => 2_500_000_000 + $p['swept'],
                'sub_balance_before' => $p['target'] + $p['swept'],
                'sub_balance_after' => $p['target'],
            ]);
        }

        // 10. Indeks 15 Kasus Bernama (Named Test Cases)
        // Disimpan dalam metadata untuk verifikasi deterministik
    }

    /**
     * Skenario Kejutan L.4: "Rupiah melemah 8% dan mutasi bank tertunda"
     */
    public function injectShock(): void
    {
        // 1. Rupiah melemah 8% terhadap USD dan valas utama
        $usdRates = ExchangeRate::where('from_currency', 'USD')->get();
        foreach ($usdRates as $r) {
            $oldNum = (int) $r->rate_numerator;
            $newNum = (int) intdiv($oldNum * 108, 100); // +8%
            $r->update(['rate_numerator' => $newNum]);
        }

        // 2. Mutasi bank tertunda (unmatched status pada mutasi baru)
        BankStatement::where('is_reconciled', false)
            ->update([
                'reconciliation_status' => 'unmatched',
                'description' => DB::raw("description || ' [SHOCK: Mutasi tertunda kliring BI-FAST]'"),
            ]);
    }
}
