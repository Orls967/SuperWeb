<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Syariah\Application\Services\NpfClassificationService;
use Modules\Syariah\Application\Services\SyariahOperationsCommandCenterService;
use Modules\Syariah\Application\Services\SyariahRestructuringService;
use Modules\Syariah\Application\Services\UniversalSyariahWalletService;
use Modules\Syariah\Domain\Models\SybHalalCategory;
use Modules\Syariah\Domain\Models\SybHalalMerchant;
use Modules\Syariah\Ledger\SyariahAccounts;

final class SyariahOperationsT1Seeder extends Seeder
{
    public function run(): void
    {
        SyariahAccounts::ensureAccountsExist();

        $commandCenter = app(SyariahOperationsCommandCenterService::class);
        $restructuringService = app(SyariahRestructuringService::class);
        $npfService = app(NpfClassificationService::class);
        $walletService = app(UniversalSyariahWalletService::class);

        // 1. Seed Kategori Halal & Non-Halal
        $categories = [
            ['code' => 'HALAL_RESTO', 'name' => 'Restoran & Makanan Halal', 'line' => 'RESTO', 'mandatory' => true, 'prohibited' => false],
            ['code' => 'HALAL_HOTEL', 'name' => 'Perhotelan Syariah', 'line' => 'HOTEL', 'mandatory' => true, 'prohibited' => false],
            ['code' => 'HALAL_VENUE', 'name' => 'Venue & Gedung Syariah', 'line' => 'VENUE', 'mandatory' => false, 'prohibited' => false],
            ['code' => 'HALAL_RETAIL', 'name' => 'Retail & Minimarket Halal', 'line' => 'RETAIL', 'mandatory' => false, 'prohibited' => false],
            ['code' => 'HALAL_HEALTH', 'name' => 'Klinik & Rumah Sakit Syariah', 'line' => 'HEALTH', 'mandatory' => true, 'prohibited' => false],
            ['code' => 'HALAL_LOGISTICS', 'name' => 'Logistik Rantai Dingin Halal', 'line' => 'LOGISTICS', 'mandatory' => true, 'prohibited' => false],
            ['code' => 'HALAL_PHARMA', 'name' => 'Farmasi & Jamu Halal', 'line' => 'HEALTH', 'mandatory' => true, 'prohibited' => false],
            ['code' => 'HALAL_FASHION', 'name' => 'Busana Muslim & Fashion', 'line' => 'RETAIL', 'mandatory' => false, 'prohibited' => false],
            ['code' => 'HALAL_COSMETICS', 'name' => 'Kosmetik Halal Terdaftar', 'line' => 'RETAIL', 'mandatory' => true, 'prohibited' => false],
            ['code' => 'HALAL_AGRI', 'name' => 'Hasil Tani & Pangan Berkelanjutan', 'line' => 'AGRI', 'mandatory' => false, 'prohibited' => false],
            ['code' => 'HALAL_TOURISM', 'name' => 'Travel Wisata Halal & Umrah', 'line' => 'TOURISM', 'mandatory' => true, 'prohibited' => false],
            ['code' => 'HALAL_EDU', 'name' => 'Pendidikan & Pesantren Syariah', 'line' => 'EDU', 'mandatory' => false, 'prohibited' => false],
            ['code' => 'GAMBLING', 'name' => 'Perjudian & Taruhan', 'line' => 'CASINO', 'mandatory' => true, 'prohibited' => true],
            ['code' => 'ALCOHOL_BAR', 'name' => 'Minuman Beralkohol & Pub', 'line' => 'BAR', 'mandatory' => true, 'prohibited' => true],
        ];

        foreach ($categories as $cat) {
            SybHalalCategory::firstOrCreate(
                ['category_code' => $cat['code']],
                [
                    'name' => $cat['name'],
                    'line_code' => $cat['line'],
                    'is_halal_mandatory' => $cat['mandatory'],
                    'is_strictly_prohibited' => $cat['prohibited'],
                ]
            );
        }

        // 2. Seed 30 Merchant Halal
        for ($m = 1; $m <= 30; $m++) {
            $cat = $categories[($m - 1) % 12];
            $code = sprintf('MCH-HALAL-%03d', $m);

            SybHalalMerchant::firstOrCreate(
                ['merchant_code' => $code],
                [
                    'name' => "Mitra Syariah {$cat['name']} #{$m}",
                    'category_code' => $cat['code'],
                    'line_code' => $cat['line'],
                    'certificate_number' => sprintf('ID3111%010d', 1000000000 + $m),
                    'issuing_authority' => 'BPJPH',
                    'valid_from' => Carbon::now()->subMonths(6)->toDateString(),
                    'expires_at' => Carbon::now()->addMonths(18)->toDateString(),
                    'status' => 'ACTIVE',
                    'is_blacklisted' => false,
                ]
            );
        }

        // 2 Merchant Blacklist
        SybHalalMerchant::firstOrCreate(
            ['merchant_code' => 'MCH-BL-GAMBLING-01'],
            [
                'name' => 'Kasino Judi Malam Terlarang',
                'category_code' => 'GAMBLING',
                'line_code' => 'CASINO',
                'status' => 'ACTIVE',
                'is_blacklisted' => true,
                'blacklist_reason' => 'Perjudian dilarang mutlak dalam syariat Islam',
            ]
        );

        // 3. Seed 500 Transaksi Universal Wallet
        for ($i = 1; $i <= 500; $i++) {
            $merchantIdx = (($i - 1) % 30) + 1;
            $mchCode = sprintf('MCH-HALAL-%03d', $merchantIdx);
            $cat = $categories[($merchantIdx - 1) % 12];
            $orderRef = sprintf('ORD-WAL-%06d', $i);
            $amount = (50_000 + (($i * 137) % 950_000)) * 100; // Rp 50.000 - Rp 1.000.000

            $walletService->pay(
                customerAccountRef: sprintf('WALLET-USER-%03d', (($i - 1) % 50) + 1),
                merchantCode: $mchCode,
                businessLineCode: $cat['line'],
                amountMinor: $amount,
                orderReference: $orderRef,
                metadata: ['source' => 'T1_SIMULATION', 'line' => $cat['line']]
            );
        }

        // 4. Seed Portofolio Kontrak & Evaluasi NPF (100 Kontrak)
        $contracts = [];
        for ($k = 1; $k <= 100; $k++) {
            // Distribusi DPD deterministik: 75 Lancar (0), 15 DPK (1-90), 5 Kurang Lancar (91-120), 3 Diragukan (121-180), 2 Macet (>180)
            if ($k <= 75) {
                $dpd = 0;
            } elseif ($k <= 90) {
                $dpd = 10 + ($k % 80);
            } elseif ($k <= 95) {
                $dpd = 95 + ($k % 25);
            } elseif ($k <= 98) {
                $dpd = 130 + ($k % 50);
            } else {
                $dpd = 200 + ($k % 100);
            }

            $principal = (10_000_000 + (($k * 97) % 90_000_000)) * 100; // Rp 10jt - Rp 100jt
            $margin = (int) intval(round($principal * 0.12));

            $contracts[] = [
                'contract_ref' => sprintf('FIN-PRT-%04d', $k),
                'customer_ref' => sprintf('CUST-%03d', (($k - 1) % 40) + 1),
                'product_type' => ['MURABAHAH', 'IJARAH', 'MICRO', 'SCF', 'AGRI'][$k % 5],
                'dpd' => $dpd,
                'outstanding_principal_minor' => $principal,
                'remaining_margin_minor' => $margin,
            ];
        }

        $npfService->evaluateAndClassify('2026-11-T1', $contracts, 'T1_DETERMINISTIC_SEEDER');

        // 5. Seed 20 Permohonan Restrukturisasi
        for ($r = 1; $r <= 20; $r++) {
            $finRef = sprintf('FIN-RST-%03d', $r);
            $principal = (50_000_000 + ($r * 5_000_000)) * 100;
            $margin = (int) intval(round($principal * 0.15));

            $req = $restructuringService->requestRestructuring(
                financingRef: $finRef,
                customerRef: sprintf('CUST-RST-%02d', $r),
                contractType: 'MURABAHAH',
                originalOutstandingPrincipalMinor: $principal,
                originalRemainingMarginMinor: $margin,
                marginHaircutBps: 2500, // 25% Haircut
                originalTenorMonths: 12,
                newTenorMonths: 18,
                gracePeriodMonths: 2,
                reason: "Restrukturisasi pemulihan dampak siklus bisnis #{$r}",
                requestedBy: 'RISK_OFFICER_01'
            );

            if ($r <= 15) {
                $restructuringService->reviewByDps($req->request_ref, 'DPS-DR-AZIZ', 'Sesuai fatwa DSN-MUI restrukturisasi pembiayaan', 'DPS_REVIEWED');
            }

            if ($r <= 10) {
                $restructuringService->approve($req->request_ref);
                $restructuringService->execute($req->request_ref);

                // Bayar cicilan bulan ke-3
                $restructuringService->payInstallment($req->request_ref, 3);
            }
        }

        // 6. Seed Catatan Reviu DPS & Insiden Operasional
        $commandCenter->recordDpsReview(
            periodLabel: '2026-Q4-T1',
            reviewType: 'QUARTERLY_SUPERVISION',
            reviewerName: 'Prof. Dr. KH. Didin Hafidhuddin',
            verdict: 'COMPLIANT',
            findings: 'Seluruh operasional perbankan syariah, dompet 30 lini, dan restrukturisasi bebas bunga telah diaudit tanpa pelanggaran syariat.',
            recommendations: 'Perluas jangkauan sertifikasi halal ke merchant UMKM binaan.'
        );

        $commandCenter->recordIncident(
            severity: 'MEDIUM',
            category: 'MERCHANT_CERTIFICATE_WARNING',
            description: 'Peringatan otomatis: 3 sertifikat halal merchant akan berakhir dalam 30 hari.',
            entityReference: 'MCH-HALAL-015'
        );
    }
}
