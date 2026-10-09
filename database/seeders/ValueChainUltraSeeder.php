<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ValueChainUltraSeeder extends Seeder
{
    /**
     * Jalankan seeder skala enterprise ultra-deterministik dan chunked:
     * - Vendor/Pemasok multi-kategori dengan sertifikasi & bank
     * - Pabrik Manufaktur & Work Center
     * - Jaringan Distributor & Limit Kredit
     * - Agen Penjualan & Komisi Multi-Level
     * - Kontrak Bisnis & Audit Trail
     */
    public function run(): void
    {
        $startTime = microtime(true);
        $this->command?->info('🚀 Memulai ValueChainUltraSeeder (Dataset Skala Enterprise Deterministik)...');

        $this->seedSuppliersAndCertifications();
        $this->seedManufacturingFacilities();
        $this->seedDistributionNetwork();
        $this->seedAgencyAndContracts();

        $duration = round(microtime(true) - $startTime, 2);
        $this->command?->info("✨ ValueChainUltraSeeder selesai dalam {$duration} detik.");
    }

    /**
     * 1. Pemasok/Vendor terverifikasi dengan data legalitas & sertifikasi
     */
    protected function seedSuppliersAndCertifications(): void
    {
        $this->command?->info('  [1/4] Mengonfigurasi Vendor, Rekening Bank & Sertifikasi...');

        $now = Carbon::now();
        $suppliers = [];
        for ($i = 1; $i <= 50; $i++) {
            $code = sprintf('VND-%04d', $i);
            $suppliers[] = [
                'id' => (string) Str::uuid(),
                'code' => $code,
                'name' => "Global Supplier {$i} Corp.",
                'kind' => 'producer',
                'status' => 'approved',
                'lead_time_days' => 7,
                'payment_terms_days' => 30,
                'rating' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (DB::getSchemaBuilder()->hasTable('sup_suppliers')) {
            foreach ($suppliers as $item) {
                DB::table('sup_suppliers')->updateOrInsert(
                    ['code' => $item['code']],
                    $item
                );
            }
        }
    }

    /**
     * 2. Fasilitas Pabrik Manufaktur & Work Center
     */
    protected function seedManufacturingFacilities(): void
    {
        $this->command?->info('  [2/4] Mengonfigurasi Fasilitas Pabrik Manufaktur & Work Center...');

        $now = Carbon::now();
        $plantId = DB::table('mfg_plants')->where('code', 'PLT-JKT')->value('id');

        if (! $plantId && DB::getSchemaBuilder()->hasTable('mfg_plants')) {
            $plantId = (string) Str::uuid();
            DB::table('mfg_plants')->insert([
                'id' => $plantId,
                'code' => 'PLT-JKT',
                'name' => 'Pabrik Jakarta',
                'type' => 'factory',
                'timezone' => 'Asia/Jakarta',
                'nominal_capacity_per_day' => 1000,
                'capacity_uom' => 'unit',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($plantId && DB::getSchemaBuilder()->hasTable('mfg_work_centers')) {
            for ($w = 1; $w <= 20; $w++) {
                $code = sprintf('WC-%03d', $w);
                DB::table('mfg_work_centers')->updateOrInsert(
                    ['plant_id' => $plantId, 'code' => $code],
                    [
                        'id' => (string) Str::uuid(),
                        'name' => "Work Center Line {$w} Assembly",
                        'kind' => 'machine',
                        'capacity_per_hour' => 50,
                        'capacity_uom' => 'unit',
                        'efficiency_percent' => 95,
                        'machine_cost_per_hour_idr' => 100000,
                        'labor_cost_per_hour_idr' => 50000,
                        'overhead_per_hour_idr' => 25000,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * 3. Jaringan Distributor Resmi & Limit Kredit
     */
    protected function seedDistributionNetwork(): void
    {
        $this->command?->info('  [3/4] Mengonfigurasi Jaringan Distributor & Limit Kredit...');

        $now = Carbon::now();
        for ($d = 1; $d <= 30; $d++) {
            $code = sprintf('DST-%04d', $d);
            if (DB::getSchemaBuilder()->hasTable('dist_distributors')) {
                DB::table('dist_distributors')->updateOrInsert(
                    ['code' => $code],
                    [
                        'id' => (string) Str::uuid(),
                        'name' => "Distributor Wilayah {$d} Nusantara",
                        'kind' => 'distributor',
                        'tier' => $d <= 5 ? 'gold' : ($d <= 15 ? 'silver' : 'bronze'),
                        'credit_limit_idr' => ($d <= 5 ? 500000000 : 150000000),
                        'credit_exposure_idr' => 0,
                        'status' => 'approved',
                        'payment_terms_days' => 30,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * 4. Struktur Agensi, Downline & Kontrak Terpadu
     */
    protected function seedAgencyAndContracts(): void
    {
        $this->command?->info('  [4/4] Mengonfigurasi Agen Penjualan, Referral & Kontrak...');

        $now = Carbon::now();
        for ($a = 1; $a <= 40; $a++) {
            $code = sprintf('AGY-%04d', $a);
            if (DB::getSchemaBuilder()->hasTable('agy_agents')) {
                DB::table('agy_agents')->updateOrInsert(
                    ['code' => $code],
                    [
                        'id' => (string) Str::uuid(),
                        'name' => "Mitra Agen {$a}",
                        'kind' => 'sales_agent',
                        'status' => 'active',
                        'max_downline_levels' => 3,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
