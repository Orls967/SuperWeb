<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyariahMurabahahT1Seeder extends Seeder
{
    /**
     * D19 Simulasi Skala T1: 1.000 akad murabahah aktif/lunas beserta jadwal angsuran dan saldo ledger konsisten.
     */
    public function run(): void
    {
        $now = '2026-11-04 00:00:00';

        $officerId = (int) (User::query()->where('email', 't1-syb-officer@superweb.test')->value('id')
            ?? User::factory()->create([
                'name' => 'T1 Syariah Officer',
                'email' => 't1-syb-officer@superweb.test',
                'role' => 'syariah_officer',
            ])->id);

        $dpsId = (int) (User::query()->where('email', 't1-syb-dps@superweb.test')->value('id')
            ?? User::factory()->create([
                'name' => 'T1 DPS Reviewer',
                'email' => 't1-syb-dps@superweb.test',
                'role' => 'dps_reviewer',
            ])->id);

        $nasabahId = (int) (User::query()->where('email', 't1-syb-nasabah@superweb.test')->value('id')
            ?? User::factory()->create([
                'name' => 'T1 Nasabah Syariah',
                'email' => 't1-syb-nasabah@superweb.test',
                'role' => 'nasabah',
            ])->id);

        // Pastikan 1.000 akad hanya dibuat bila belum ada (idempoten)
        if (DB::table('syb_murabahah_financings')->where('contract_code', 'like', 'MRB-T1-%')->exists()) {
            return;
        }

        $startId = ((int) DB::table('syb_murabahah_financings')->max('id')) + 1;

        $contracts = [];
        $schedules = [];
        $ledgerAccounts = [];
        $totalRecognizedMargin = 0;

        for ($i = 1; $i <= 1000; $i++) {
            $contractId = $startId + ($i - 1);
            $code = sprintf('MRB-T1-%04d', $i);

            // Biaya 60.000.000, margin 12.000.000, tenor 6 bulan (10.000.000 pokok + 2.000.000 margin = 12.000.000/bulan)
            $cost = 60_000_000;
            $margin = 12_000_000;
            $sellingPrice = 72_000_000;
            $tenor = 6;

            // Angsuran ke-1 sudah dibayar (12.000.000), angsuran 2..6 belum dibayar (60.000.000)
            $paidTotal = 12_000_000;
            $recognizedMargin = 2_000_000;
            $unpaidReceivable = 60_000_000;
            $deferredMargin = 10_000_000;
            $totalRecognizedMargin += $recognizedMargin;

            $contractHash = hash('sha256', "{$code}:{$nasabahId}:{$cost}:{$margin}:{$sellingPrice}:{$tenor}:{$dpsId}");

            $contracts[] = [
                'id' => $contractId,
                'contract_code' => $code,
                'customer_user_id' => $nasabahId,
                'supplier_party_ref' => ($i % 20) + 1,
                'asset_description' => sprintf('Unit Mesin Produksi Halal T1 #%04d', $i),
                'cost_price_idr' => $cost,
                'margin_amount_idr' => $margin,
                'total_selling_price_idr' => $sellingPrice,
                'tenor_months' => $tenor,
                'status' => 'ACTIVE',
                'proposed_by_user_id' => $officerId,
                'officer_approved_by_user_id' => $officerId,
                'officer_approved_at' => $now,
                'dps_reviewer_user_id' => $dpsId,
                'dps_reviewed_at' => $now,
                'dps_opinion_note' => 'Sesuai Fatwa DSN-MUI No. 04/DSN-MUI/IV/2000 tentang Murabahah.',
                'purchased_at' => $now,
                'signed_at' => $now,
                'settled_at' => null,
                'total_paid_idr' => $paidTotal,
                'recognized_margin_idr' => $recognizedMargin,
                'total_tazir_idr' => 0,
                'contract_hash' => $contractHash,
                'idempotency_key' => sprintf('syb:t1:mrb:%04d', $i),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            for ($m = 1; $m <= $tenor; $m++) {
                $isPaid = $m === 1;
                $schedules[] = [
                    'contract_id' => $contractId,
                    'installment_no' => $m,
                    'due_date' => sprintf('2027-%02d-04', $m),
                    'principal_component_idr' => 10_000_000,
                    'margin_component_idr' => 2_000_000,
                    'installment_amount_idr' => 12_000_000,
                    'paid_amount_idr' => $isPaid ? 12_000_000 : 0,
                    'status' => $isPaid ? 'PAID' : 'UNPAID',
                    'paid_at' => $isPaid ? $now : null,
                    'payment_idempotency_key' => $isPaid ? sprintf('syb:t1:pay:%04d:1', $i) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $ledgerAccounts[] = [
                'uuid' => (string) Str::uuid(),
                'code' => "syb:murabahah_receivable:{$contractId}:IDR",
                'asset_code' => 'IDR',
                'kind' => 'loan_receivable',
                'name' => "Piutang Murabahah Akad #{$contractId}",
                'allow_negative' => true,
                'cached_balance' => (string) (-$unpaidReceivable),
                'is_frozen' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $ledgerAccounts[] = [
                'uuid' => (string) Str::uuid(),
                'code' => "syb:deferred_margin:{$contractId}:IDR",
                'asset_code' => 'IDR',
                'kind' => 'liability',
                'name' => "Margin Murabahah Ditangguhkan Akad #{$contractId}",
                'allow_negative' => false,
                'cached_balance' => (string) $deferredMargin,
                'is_frozen' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($contracts, 200) as $chunk) {
            DB::table('syb_murabahah_financings')->insert($chunk);
        }
        foreach (array_chunk($schedules, 300) as $chunk) {
            DB::table('syb_murabahah_schedules')->insert($chunk);
        }
        foreach (array_chunk($ledgerAccounts, 300) as $chunk) {
            DB::table('bank_ledger_accounts')->insertOrIgnore($chunk);
        }

        // Tambahkan total margin diakui ke syb:margin_income:IDR agar selaras dengan akad yang bereits ada
        $existingIncome = (int) DB::table('bank_ledger_accounts')->where('code', 'syb:margin_income:IDR')->value('cached_balance');
        DB::table('bank_ledger_accounts')
            ->where('code', 'syb:margin_income:IDR')
            ->update(['cached_balance' => (string) ($existingIncome + $totalRecognizedMargin)]);
    }
}
