<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\TradeFinance\Application\Services\Clearing\CbamDeclarationService;

class ClearingCbamT1Seeder extends Seeder
{
    /**
     * D83 Simulasi T1: 20 koridor, 100 L/C + escrow USDT, dan 200 kontainer deklarasi CBAM.
     */
    public function run(): void
    {
        $now = '2026-11-04 00:00:00';

        // 1. Pastikan pengguna importir & eksportir tersedia
        $importerId = (int) (User::query()->where('email', 't1-importer@superweb.test')->value('id')
            ?? User::factory()->create([
                'name' => 'T1 Importer Clearing',
                'email' => 't1-importer@superweb.test',
                'role' => 'importer',
            ])->id);

        $exporterId = (int) (User::query()->where('email', 't1-exporter@superweb.test')->value('id')
            ?? User::factory()->create([
                'name' => 'T1 Exporter Clearing',
                'email' => 't1-exporter@superweb.test',
                'role' => 'exporter',
            ])->id);

        // 2. 20 Koridor & faktor emisi terverifikasi K7 (esg_verified_product_emissions)
        $origins = ['ID', 'MY', 'VN', 'TH', 'SG', 'PH', 'IN', 'CN', 'KR', 'JP'];
        $products = ['HS7601-ALUMINIUM', 'HS7208-FLAT-STEEL'];
        $emissions = [];
        $corridorIdx = 0;
        foreach ($origins as $origin) {
            foreach ($products as $product) {
                $corridorIdx++;
                $tco2e = $product === 'HS7601-ALUMINIUM' ? '1.850000' : '2.100000';
                $emissions[] = [
                    'product_code' => $product,
                    'origin_country' => $origin,
                    't_co2e_per_t' => $tco2e,
                    'verification_ref' => sprintf('ESG-K7-T1-%02d', $corridorIdx),
                    'verified_at' => '2026-01-01 00:00:00',
                    'effective_from' => '2026-01-01 00:00:00',
                    'effective_to' => null,
                    'source_label' => 'S2',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('esg_verified_product_emissions')->insertOrIgnore($emissions);

        // 3. Parameter CBAM berversi S2
        DB::table('tf_cbam_parameters')->insertOrIgnore([
            [
                'version' => 'CBAM-S2-2026Q4',
                'price_eur_cents_per_tco2e' => 8_500,
                'eur_to_idr_rate' => 17_200,
                'label' => 'S2',
                'source_label' => 'EU ETS Quarterly Reference Simulation S2',
                'effective_from' => '2026-01-01 00:00:00',
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 4. 100 L/C + 100 Escrow USDT berstatus DEPOSITED + akun ledger tf:escrow:{lc_id}:USDT seimbang
        $lcs = [];
        $escrows = [];
        $ledgerAccounts = [];

        for ($i = 1; $i <= 100; $i++) {
            $lcId = sprintf('00000000-0000-4000-8300-%012d', $i);
            $lcNumber = sprintf('LC-T1-83-%04d', $i);
            $usdtMicro = (10_000 + ($i * 100)) * 1_000_000;
            $rateIdr = 16_200;

            $lcs[] = [
                'id' => $lcId,
                'lc_number' => $lcNumber,
                'type' => 'sight',
                'issuing_bank' => 'Bank Superweb Indonesia',
                'advising_bank' => 'Rotterdam Trade Bank NV',
                'applicant_name' => 'PT Importir Nusantara',
                'beneficiary_name' => 'Global Metals BV',
                'currency' => 'USD',
                'amount_foreign' => 10_000 + ($i * 100),
                'amount_functional_idr' => (10_000 + ($i * 100)) * $rateIdr,
                'issue_date' => '2026-11-01',
                'expiry_date' => '2027-05-01',
                'tenor_days' => 90,
                'status' => 'issued',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $escrows[] = [
                'escrow_code' => sprintf('CLR-T1-%04d', $i),
                'letter_of_credit_id' => $lcId,
                'importer_user_id' => $importerId,
                'exporter_user_id' => $exporterId,
                'usdt_micro' => $usdtMicro,
                'deposit_fx_rate_idr' => $rateIdr,
                'status' => 'DEPOSITED',
                'deposit_idempotency_key' => sprintf('tf:t1:escrow:dep:%04d', $i),
                'deposited_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $ledgerAccounts[] = [
                'uuid' => (string) Str::uuid(),
                'code' => "tf:escrow:{$lcId}:USDT",
                'asset_code' => 'USDT',
                'kind' => 'LIABILITY',
                'name' => "Escrow Clearing L/C {$lcNumber}",
                'allow_negative' => false,
                'cached_balance' => (string) $usdtMicro,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('tf_letters_of_credit')->insertOrIgnore($lcs);
        DB::table('tf_clearing_escrows')->insertOrIgnore($escrows);
        DB::table('bank_ledger_accounts')->insertOrIgnore($ledgerAccounts);

        // 5. 200 Kontainer deklarasi CBAM
        $declarations = [];
        for ($c = 1; $c <= 200; $c++) {
            $origin = $origins[($c - 1) % count($origins)];
            $product = $products[($c - 1) % count($products)];
            $specific = $product === 'HS7601-ALUMINIUM' ? '1.850000' : '2.100000';
            $corridorNo = ((($c - 1) % count($origins)) * 2) + ((($c - 1) % count($products)) + 1);
            $verRef = sprintf('ESG-K7-T1-%02d', $corridorNo);

            $netMassKg = 20_000 + (($c % 10) * 1_000);
            $milli = CbamDeclarationService::embeddedMilliTco2e($netMassKg, $specific);
            $levy = CbamDeclarationService::levyIdr($milli, 8_500, 17_200);
            $certNo = sprintf('CBAM/2026/T1%04d', $c);
            $containerNo = sprintf('MSCU83%05d', $c);

            $docContent = json_encode([
                'certificate_number' => $certNo,
                'container_number' => $containerNo,
                'product_code' => $product,
                'origin_country' => $origin,
                'net_mass_kg' => $netMassKg,
                'specific_t_co2e_per_t' => $specific,
                'verification_ref' => $verRef,
                'embedded_milli_tco2e' => $milli,
                'parameter_version' => 'CBAM-S2-2026Q4',
                'levy_idr' => $levy,
                'simulation_label' => 'S2',
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $checksum = hash('sha256', $docContent);
            $certHash = CbamDeclarationService::certificateHash([
                'certificate_number' => $certNo,
                'container_number' => $containerNo,
                'product_code' => $product,
                'origin_country' => $origin,
                'net_mass_kg' => $netMassKg,
                'specific_t_co2e_per_t' => $specific,
                'verification_ref' => $verRef,
                'embedded_milli_tco2e' => $milli,
                'parameter_version' => 'CBAM-S2-2026Q4',
                'levy_idr' => $levy,
                'document_checksum' => $checksum,
            ]);

            $declarations[] = [
                'certificate_number' => $certNo,
                'container_number' => $containerNo,
                'product_code' => $product,
                'origin_country' => $origin,
                'net_mass_kg' => $netMassKg,
                'specific_t_co2e_per_t' => $specific,
                'verification_ref' => $verRef,
                'embedded_milli_tco2e' => $milli,
                'parameter_version' => 'CBAM-S2-2026Q4',
                'levy_idr' => $levy,
                'simulation_label' => 'S2',
                'document_checksum' => $checksum,
                'certificate_hash' => $certHash,
                'idempotency_key' => sprintf('tf:t1:cbam:%04d', $c),
                'declared_by_user_id' => $importerId,
                'declared_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($declarations, 100) as $chunk) {
            DB::table('tf_cbam_declarations')->insertOrIgnore($chunk);
        }
    }
}
