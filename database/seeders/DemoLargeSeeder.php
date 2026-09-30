<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\ParkingZone;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Modules\Mall\Domain\Models\Zone;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\RestoTable;

class DemoLargeSeeder extends Seeder
{
    /**
     * Jalankan seeder skala enterprise:
     * - 3 Outlet Resto + 1 Central Kitchen
     * - 60 Tenant Mall & Unit Komersial
     * - 12 Bulan Riwayat Tagihan Bulanan (Billing History)
     * - 150.000 Transaksi Sesi Parkir (Bulk Insert Teroptimasi)
     */
    public function run(): void
    {
        $startTime = microtime(true);
        $this->command?->info('🚀 Memulai DemoLargeSeeder (Skala Enterprise)...');

        // Panggil seeder dasar hanya jika database belum di-seed
        if (! User::where('email', 'admin@autoserve.test')->exists()) {
            $this->call([
                DatabaseSeeder::class,
            ]);
        }

        $this->seedRestoOutlets();
        $property = Property::where('code', 'DM-BJM')->firstOrFail();
        $leases = $this->seedMallTenantsAndUnits($property);
        $this->seedTwelveMonthsBilling($leases);
        $this->seedLargeParkingSessions($property);

        $duration = round(microtime(true) - $startTime, 2);
        $this->command?->info("✨ DemoLargeSeeder selesai dalam {$duration} detik.");
    }

    /**
     * 1. 3 Outlet Resto + 1 Central Kitchen
     */
    protected function seedRestoOutlets(): void
    {
        $this->command?->info('  [1/4] Mengonfigurasi 3 Outlet Resto & 1 Central Kitchen...');

        $outlets = [
            [
                'code' => 'CK-01',
                'name' => 'RM Sari Ranah — Dapur Sentral Veteran',
                'address' => 'Jl. Veteran No. 45, Banjarmasin',
                'type' => 'central_kitchen',
                'tables' => 0,
            ],
            [
                'code' => 'DM-01',
                'name' => 'RM Sari Ranah — Duta Mall Banjarmasin',
                'address' => 'Duta Mall Lantai LG Unit LG-12',
                'type' => 'mall_outlet',
                'tables' => 20,
            ],
            [
                'code' => 'KD-01',
                'name' => 'RM Sari Ranah — Kayutangi',
                'address' => 'Jl. Brigjend H. Hasan Basri No. 12, Kayutangi',
                'type' => 'dine_in',
                'tables' => 25,
            ],
        ];

        foreach ($outlets as $data) {
            $outlet = Outlet::updateOrCreate(
                ['code' => $data['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'phone' => '0811555'.rand(1000, 9999),
                    'is_active' => true,
                ]
            );

            // Buat meja jika outlet melayani dine-in
            if ($data['tables'] > 0) {
                for ($t = 1; $t <= $data['tables']; $t++) {
                    RestoTable::firstOrCreate(
                        ['outlet_id' => $outlet->id, 'code' => sprintf('T%02d', $t)],
                        [
                            'seats' => rand(2, 6),
                            'zone' => 'Utama',
                            'status' => 'available',
                        ]
                    );
                }
            }
        }
    }

    /**
     * 2. 60 Tenant Mall & Unit Komersial
     *
     * @return array<Lease>
     */
    protected function seedMallTenantsAndUnits(Property $property): array
    {
        $this->command?->info('  [2/4] Mendaftarkan 60 Tenant & Unit Komersial Duta Mall...');

        $adminUser = User::where('role', 'admin')->first();

        // Pastikan zona lantai tersedia
        $zones = [
            'LG' => Zone::firstOrCreate(['property_id' => $property->id, 'floor' => 'LG'], ['name' => 'Food Court & Services LG', 'color_code' => '#f59e0b']),
            'GF' => Zone::firstOrCreate(['property_id' => $property->id, 'floor' => 'GF'], ['name' => 'Ground Floor Fashion & Anchor', 'color_code' => '#3b82f6']),
            'L1' => Zone::firstOrCreate(['property_id' => $property->id, 'floor' => 'L1'], ['name' => 'Level 1 Gadget & Lifestyle', 'color_code' => '#10b981']),
            'L2' => Zone::firstOrCreate(['property_id' => $property->id, 'floor' => 'L2'], ['name' => 'Level 2 Cinema, Kids & Entertainment', 'color_code' => '#8b5cf6']),
        ];

        $tenantBrands = [
            // F&B (15)
            ['RM Sari Ranah', TenantCategory::FNB, 'DM-01', 'LG', 120.0, 350000, 95000, RentModel::GREATER_OF, 10.0],
            ['Starbucks Coffee', TenantCategory::FNB, 'STARBUCKS-DM', 'GF', 150.0, 500000, 120000, RentModel::GREATER_OF, 8.5],
            ['Chatime Milk Tea', TenantCategory::FNB, 'CHATIME-DM', 'LG', 40.0, 320000, 90000, RentModel::FIXED, 0.0],
            ['Kopi Kenangan', TenantCategory::FNB, 'KENANGAN-DM', 'LG', 35.0, 320000, 90000, RentModel::FIXED, 0.0],
            ['Janji Jiwa & Jiwa Toast', TenantCategory::FNB, 'JIWA-DM', 'LG', 40.0, 310000, 90000, RentModel::FIXED, 0.0],
            ['Shihlin Taiwan Street Snacks', TenantCategory::FNB, 'SHIHLIN-DM', 'LG', 45.0, 330000, 90000, RentModel::FIXED, 0.0],
            ['Solaria Family Resto', TenantCategory::FNB, 'SOLARIA-DM', 'L1', 180.0, 380000, 100000, RentModel::GREATER_OF, 9.0],
            ['Pizza Hut Restaurant', TenantCategory::FNB, 'PIZZAHUT-DM', 'GF', 220.0, 480000, 115000, RentModel::GREATER_OF, 8.0],
            ['KFC Store Duta Mall', TenantCategory::FNB, 'KFC-DM', 'GF', 200.0, 490000, 115000, RentModel::GREATER_OF, 8.0],
            ['McDonalds Duta Mall', TenantCategory::FNB, 'MCD-DM', 'GF', 250.0, 520000, 120000, RentModel::GREATER_OF, 8.0],
            ['Excelso Coffee', TenantCategory::FNB, 'EXCELSO-DM', 'L1', 110.0, 400000, 105000, RentModel::FIXED, 0.0],
            ['J.CO Donuts & Coffee', TenantCategory::FNB, 'JCO-DM', 'GF', 160.0, 490000, 115000, RentModel::GREATER_OF, 8.5],
            ['BreadTalk Bakery', TenantCategory::FNB, 'BREADTALK-DM', 'LG', 75.0, 340000, 95000, RentModel::FIXED, 0.0],
            ['Bakso Lapangan Tembak', TenantCategory::FNB, 'BAKSO-DM', 'LG', 80.0, 310000, 90000, RentModel::FIXED, 0.0],
            ['Kintan Buffet & Shaburi', TenantCategory::FNB, 'KINTAN-DM', 'L1', 240.0, 420000, 110000, RentModel::GREATER_OF, 10.0],

            // Fashion & Apparel (15)
            ['Uniqlo Duta Mall', TenantCategory::FASHION, 'UNIQLO-DM', 'GF', 650.0, 450000, 110000, RentModel::GREATER_OF, 7.0],
            ['H&M Hennes & Mauritz', TenantCategory::FASHION, 'HM-DM', 'GF', 550.0, 450000, 110000, RentModel::GREATER_OF, 7.0],
            ['Zara Ritel Indonesia', TenantCategory::FASHION, 'ZARA-DM', 'GF', 500.0, 480000, 115000, RentModel::GREATER_OF, 7.5],
            ['Pull & Bear', TenantCategory::FASHION, 'PULLBEAR-DM', 'GF', 280.0, 460000, 110000, RentModel::FIXED, 0.0],
            ['Bershka', TenantCategory::FASHION, 'BERSHKA-DM', 'GF', 260.0, 460000, 110000, RentModel::FIXED, 0.0],
            ['Stradivarius', TenantCategory::FASHION, 'STRADIVARIUS-DM', 'GF', 240.0, 460000, 110000, RentModel::FIXED, 0.0],
            ['Cotton On', TenantCategory::FASHION, 'COTTONON-DM', 'L1', 220.0, 390000, 100000, RentModel::FIXED, 0.0],
            ['Giordano Indonesia', TenantCategory::FASHION, 'GIORDANO-DM', 'L1', 110.0, 380000, 95000, RentModel::FIXED, 0.0],
            ['The Executive', TenantCategory::FASHION, 'EXECUTIVE-DM', 'L1', 130.0, 380000, 95000, RentModel::FIXED, 0.0],
            ['Colorbox Fashion', TenantCategory::FASHION, 'COLORBOX-DM', 'L1', 95.0, 360000, 95000, RentModel::FIXED, 0.0],
            ['3Second Flagship', TenantCategory::FASHION, '3SECOND-DM', 'L1', 150.0, 370000, 95000, RentModel::FIXED, 0.0],
            ['Hammer Clothes', TenantCategory::FASHION, 'HAMMER-DM', 'L1', 85.0, 350000, 90000, RentModel::FIXED, 0.0],
            ['Matahari Department Store', TenantCategory::FASHION, 'MATAHARI-DM', 'L2', 1200.0, 300000, 85000, RentModel::REVENUE_SHARE, 6.0],
            ['Wacoal Butik', TenantCategory::FASHION, 'WACOAL-DM', 'L1', 60.0, 360000, 95000, RentModel::FIXED, 0.0],
            ['Miniso Lifestyle', TenantCategory::FASHION, 'MINISO-DM', 'L1', 160.0, 400000, 100000, RentModel::FIXED, 0.0],

            // Gadgets & Electronics (10)
            ['iBox Apple Premium Partner', TenantCategory::ELECTRONICS, 'IBOX-DM', 'L1', 180.0, 450000, 110000, RentModel::GREATER_OF, 5.0],
            ['Samsung Experience Store', TenantCategory::ELECTRONICS, 'SAMSUNG-DM', 'L1', 140.0, 420000, 105000, RentModel::GREATER_OF, 5.0],
            ['Xiaomi Authorized Reseller', TenantCategory::ELECTRONICS, 'XIAOMI-DM', 'L1', 110.0, 390000, 100000, RentModel::FIXED, 0.0],
            ['Erafone Megastore', TenantCategory::ELECTRONICS, 'ERAFONE-DM', 'L1', 200.0, 400000, 105000, RentModel::FIXED, 0.0],
            ['Digimap Apple Reseller', TenantCategory::ELECTRONICS, 'DIGIMAP-DM', 'L1', 150.0, 430000, 105000, RentModel::FIXED, 0.0],
            ['Asus ROG Gaming Store', TenantCategory::ELECTRONICS, 'ROG-DM', 'L1', 90.0, 380000, 95000, RentModel::FIXED, 0.0],
            ['Oppo Brand Store', TenantCategory::ELECTRONICS, 'OPPO-DM', 'L1', 75.0, 370000, 95000, RentModel::FIXED, 0.0],
            ['Vivo Brand Store', TenantCategory::ELECTRONICS, 'VIVO-DM', 'L1', 75.0, 370000, 95000, RentModel::FIXED, 0.0],
            ['Acer Exclusive Store', TenantCategory::ELECTRONICS, 'ACER-DM', 'L1', 80.0, 360000, 95000, RentModel::FIXED, 0.0],
            ['Electronic City', TenantCategory::ELECTRONICS, 'ELECCITY-DM', 'L2', 450.0, 350000, 95000, RentModel::REVENUE_SHARE, 5.0],

            // Automotive & Services (8)
            ['AutoServe Express Bengkel Mall', TenantCategory::AUTOMOTIVE, 'AUTOSERVE-DM', 'LG', 250.0, 200000, 70000, RentModel::GREATER_OF, 8.0],
            ['Autoglaze Express Cuci Mobil', TenantCategory::AUTOMOTIVE, 'AUTOGLAZE-DM', 'LG', 180.0, 220000, 75000, RentModel::FIXED, 0.0],
            ['Ace Hardware Home Center', TenantCategory::SERVICES, 'ACE-DM', 'L2', 750.0, 320000, 90000, RentModel::FIXED, 0.0],
            ['Informa Furnishings', TenantCategory::SERVICES, 'INFORMA-DM', 'L2', 650.0, 310000, 90000, RentModel::FIXED, 0.0],
            ['Guardian Health & Beauty', TenantCategory::SERVICES, 'GUARDIAN-DM', 'LG', 90.0, 360000, 95000, RentModel::FIXED, 0.0],
            ['Watsons Indonesia', TenantCategory::SERVICES, 'WATSONS-DM', 'LG', 95.0, 360000, 95000, RentModel::FIXED, 0.0],
            ['Optik Seis', TenantCategory::SERVICES, 'OPTIKSEIS-DM', 'GF', 70.0, 420000, 105000, RentModel::FIXED, 0.0],
            ['Optik Melawai', TenantCategory::SERVICES, 'OPTIKMELAWAI-DM', 'GF', 75.0, 420000, 105000, RentModel::FIXED, 0.0],

            // Entertainment & Books (6)
            ['Cinema XXI & Premiere', TenantCategory::ENTERTAINMENT, 'XXI-DM', 'L2', 1500.0, 380000, 100000, RentModel::REVENUE_SHARE, 7.5],
            ['Timezone Game Arcade', TenantCategory::ENTERTAINMENT, 'TIMEZONE-DM', 'L2', 450.0, 350000, 95000, RentModel::REVENUE_SHARE, 8.0],
            ['Gramedia Books & Stationery', TenantCategory::ENTERTAINMENT, 'GRAMEDIA-DM', 'L2', 400.0, 330000, 90000, RentModel::FIXED, 0.0],
            ['Kidz Station Toys', TenantCategory::ENTERTAINMENT, 'KIDZSTATION-DM', 'L2', 180.0, 350000, 95000, RentModel::FIXED, 0.0],
            ['Funworld Family Entertainment', TenantCategory::ENTERTAINMENT, 'FUNWORLD-DM', 'L2', 380.0, 340000, 95000, RentModel::REVENUE_SHARE, 8.0],
            ['Inul Vizta Family Karaoke', TenantCategory::ENTERTAINMENT, 'VIZTA-DM', 'L2', 320.0, 320000, 90000, RentModel::FIXED, 0.0],

            // Supermarket & Footwear (6)
            ['Hypermart Duta Mall', TenantCategory::OTHER, 'HYPERMART-DM', 'LG', 1800.0, 250000, 80000, RentModel::REVENUE_SHARE, 4.0],
            ['Sports Station Flagship', TenantCategory::FASHION, 'SPORTSTATION-DM', 'L1', 250.0, 400000, 105000, RentModel::FIXED, 0.0],
            ['Payless Shoesource', TenantCategory::FASHION, 'PAYLESS-DM', 'L1', 140.0, 350000, 95000, RentModel::FIXED, 0.0],
            ['Skechers Official', TenantCategory::FASHION, 'SKECHERS-DM', 'L1', 120.0, 390000, 100000, RentModel::FIXED, 0.0],
            ['Bata Shoe Store', TenantCategory::FASHION, 'BATA-DM', 'L1', 85.0, 340000, 90000, RentModel::FIXED, 0.0],
            ['Nature Republic Beauty', TenantCategory::SERVICES, 'NATUREREPUBLIC-DM', 'GF', 65.0, 450000, 110000, RentModel::FIXED, 0.0],
        ];

        $createdLeases = [];
        $unitIdx = 1;

        foreach ($tenantBrands as $idx => $t) {
            [$name, $category, $extRef, $floor, $area, $baseRate, $scRate, $rentModel, $revSharePct] = $t;

            $unitNumber = sprintf('%s-%03d', $floor, $unitIdx + 100);
            $unitIdx++;
            $zone = $zones[$floor];

            $unit = Unit::updateOrCreate(
                ['property_id' => $property->id, 'unit_number' => $unitNumber],
                [
                    'uuid' => (string) Str::uuid(),
                    'floor' => $floor,
                    'zone_id' => $zone->id,
                    'area_sqm' => $area,
                    'base_rent_rate_per_sqm' => $baseRate,
                    'service_charge_per_sqm' => $scRate,
                    'status' => UnitStatus::LEASED,
                ]
            );

            $tenant = Tenant::updateOrCreate(
                ['external_ref' => $extRef],
                [
                    'uuid' => (string) Str::uuid(),
                    'company_name' => "PT Mitra {$name}",
                    'brand_name' => $name,
                    'category' => $category,
                    'pic_name' => "Manajer {$name}",
                    'pic_phone' => '0812'.rand(10000000, 99999999),
                    'pic_email' => strtolower(Str::slug($name)).'@dutamall-tenant.test',
                    'npwp' => sprintf('%02d.%03d.%03d.%1d-%03d.000', rand(10, 99), rand(100, 999), rand(100, 999), rand(1, 9), rand(100, 999)),
                    'user_id' => $adminUser?->id,
                    'is_active' => true,
                ]
            );

            $leaseNumber = sprintf('LSE-DM-2026-%03d', $idx + 10);
            $monthlyBaseRent = (int) round($area * $baseRate);
            $monthlyServiceCharge = (int) round($area * $scRate);

            $lease = Lease::updateOrCreate(
                ['lease_number' => $leaseNumber],
                [
                    'uuid' => (string) Str::uuid(),
                    'property_id' => $property->id,
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'rent_model' => $rentModel,
                    'start_date' => Carbon::now()->subMonths(14)->toDateString(),
                    'end_date' => Carbon::now()->addMonths(22)->toDateString(),
                    'fit_out_days' => 30,
                    'billing_day' => 1,
                    'grace_days' => 7,
                    'penalty_rate_daily_percent' => 0.10,
                    'base_monthly_rent' => $monthlyBaseRent,
                    'service_charge_monthly' => $monthlyServiceCharge,
                    'revenue_share_percent' => $revSharePct,
                    'parking_validation_hours' => 2,
                    'security_deposit_amount' => $monthlyBaseRent * 3,
                    'deposit_status' => DepositStatus::HELD,
                    'status' => LeaseStatus::ACTIVE,
                ]
            );

            $createdLeases[] = $lease;
        }

        return $createdLeases;
    }

    /**
     * 3. 12 Bulan Riwayat Tagihan Bulanan (Billing History)
     *
     * @param  array<Lease>  $leases
     */
    protected function seedTwelveMonthsBilling(array $leases): void
    {
        $this->command?->info('  [3/4] Menghasilkan 12 Bulan Riwayat Tagihan Bulanan (Billing History)...');

        $now = Carbon::now();
        $periods = [];
        for ($i = 11; $i >= 0; $i--) {
            $periods[] = $now->copy()->subMonths($i)->format('Y-m');
        }

        DB::disableQueryLog();

        // Ambil sampel 15 lease untuk diisi riwayat 12 bulan penuh agar seeder tetap cepat
        $sampleLeases = array_slice($leases, 0, 15);

        foreach ($periods as $monthIdx => $periodMonth) {
            foreach ($sampleLeases as $lease) {
                $cleanPeriod = str_replace('-', '', $periodMonth);
                $invoiceNumber = sprintf('INV-MALL-%s-%04d', $cleanPeriod, $lease->id);

                // Bulan 0-10: OVERDUE (piutang aging berjalan), Bulan 11: ISSUED (bulan berjalan)
                $status = ($monthIdx < 11) ? InvoiceStatus::OVERDUE : InvoiceStatus::ISSUED;

                $baseRent = (int) $lease->base_monthly_rent;
                $serviceCharge = (int) $lease->service_charge_monthly;
                $electricity = rand(1500000, 4500000);
                $water = rand(300000, 900000);
                $subtotal = $baseRent + $serviceCharge + $electricity + $water;
                $paidAmount = 0;

                $dueDate = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth()->setDay(8);

                $invoice = Invoice::updateOrCreate(
                    ['lease_id' => $lease->id, 'period_month' => $periodMonth],
                    [
                        'uuid' => (string) Str::uuid(),
                        'invoice_number' => $invoiceNumber,
                        'tenant_id' => $lease->tenant_id,
                        'property_id' => $lease->property_id,
                        'subtotal' => $subtotal,
                        'penalty_amount' => 0,
                        'total_amount' => $subtotal,
                        'paid_amount' => $paidAmount,
                        'status' => $status,
                        'due_date' => $dueDate,
                        'issued_at' => $dueDate->copy()->subDays(7),
                        'paid_at' => null,
                    ]
                );

                // Buat invoice lines
                $invoice->lines()->delete();

                $lines = [
                    ['type' => InvoiceLineType::BASE_RENT, 'desc' => "Sewa Pokok {$lease->unit?->unit_number}", 'qty' => 1.0, 'price' => $baseRent, 'amount' => $baseRent],
                    ['type' => InvoiceLineType::SERVICE_CHARGE, 'desc' => 'Biaya Pemeliharaan & Fasilitas', 'qty' => 1.0, 'price' => $serviceCharge, 'amount' => $serviceCharge],
                    ['type' => InvoiceLineType::ELECTRICITY, 'desc' => 'Pemakaian Listrik', 'qty' => 1.0, 'price' => $electricity, 'amount' => $electricity],
                    ['type' => InvoiceLineType::WATER, 'desc' => 'Pemakaian Air Bersih PDAM', 'qty' => 1.0, 'price' => $water, 'amount' => $water],
                ];

                foreach ($lines as $line) {
                    InvoiceLine::create([
                        'invoice_id' => $invoice->id,
                        'type' => $line['type'],
                        'description' => $line['desc'],
                        'quantity' => $line['qty'],
                        'unit_price' => $line['price'],
                        'amount' => $line['amount'],
                        'paid_amount' => 0,
                        'status' => 'unpaid',
                    ]);
                }
            }
        }
    }

    /**
     * 4. 150.000 Transaksi Sesi Parkir (Bulk Insert)
     */
    protected function seedLargeParkingSessions(Property $property): void
    {
        $targetCount = (int) env('DEMO_PARKING_SESSIONS_COUNT', 150000);
        $this->command?->info("  [4/4] Memasukkan {$targetCount} Sesi Parkir (Batch Chunked Insert)...");

        $zones = ParkingZone::where('property_id', $property->id)->get();
        if ($zones->isEmpty()) {
            $zones = collect([
                ParkingZone::create(['property_id' => $property->id, 'code' => 'P1', 'name' => 'Basement A', 'vehicle_type' => 'car', 'total_capacity' => 800, 'current_occupancy' => 50, 'is_active' => true]),
                ParkingZone::create(['property_id' => $property->id, 'code' => 'P2', 'name' => 'Gedung Parkir P1', 'vehicle_type' => 'car', 'total_capacity' => 600, 'current_occupancy' => 40, 'is_active' => true]),
                ParkingZone::create(['property_id' => $property->id, 'code' => 'P3', 'name' => 'Gedung Parkir P2', 'vehicle_type' => 'car', 'total_capacity' => 600, 'current_occupancy' => 30, 'is_active' => true]),
                ParkingZone::create(['property_id' => $property->id, 'code' => 'M1', 'name' => 'Zona Motor Timur', 'vehicle_type' => 'motorcycle', 'total_capacity' => 1500, 'current_occupancy' => 120, 'is_active' => true]),
            ]);
        }

        $zoneIds = $zones->pluck('id')->all();
        $sampleTenants = Tenant::where('property_id', $property->id)->orWhereNotNull('id')->limit(5)->pluck('id')->all();

        DB::disableQueryLog();
        DB::table('mall_parking_sessions')->where('ticket_number', 'like', 'TKT-%')->delete();

        $chunkSize = 500;
        $chunksCount = (int) ceil($targetCount / $chunkSize);
        $startDate = Carbon::now()->subYear()->timestamp;
        $endDate = Carbon::now()->timestamp;
        $rangeSeconds = max(1, $endDate - $startDate);

        $gates = ['Gate Utama', 'Gate Timur', 'Gate Barat', 'Gate Selatan'];
        $methods = ['cash', 'wallet', 'member_free', 'tenant_free'];

        DB::transaction(function () use (
            $chunksCount,
            $chunkSize,
            $targetCount,
            $startDate,
            $rangeSeconds,
            $property,
            $zoneIds,
            $sampleTenants,
            $gates,
            $methods
        ) {
            for ($c = 0; $c < $chunksCount; $c++) {
                $batch = [];
                $currentChunkRows = min($chunkSize, $targetCount - ($c * $chunkSize));

                for ($r = 0; $r < $currentChunkRows; $r++) {
                    $idx = ($c * $chunkSize) + $r + 1;
                    $randomTs = $startDate + (int) (($idx / $targetCount) * $rangeSeconds);
                    $entryTime = date('Y-m-d H:i:s', $randomTs);

                    $durationMin = rand(25, 360);
                    $exitTime = date('Y-m-d H:i:s', $randomTs + ($durationMin * 60));
                    $billedHours = (int) ceil($durationMin / 60);

                    $zoneId = $zoneIds[$idx % count($zoneIds)];
                    $isMotor = ($idx % 5 === 0);
                    $rateFirst = $isMotor ? 2000 : 5000;
                    $rateNext = $isMotor ? 1000 : 3000;
                    $baseFee = $rateFirst + (max(0, $billedHours - 1) * $rateNext);

                    $hasValidation = ($idx % 8 === 0) && ! empty($sampleTenants);
                    $freeHours = $hasValidation ? 2 : 0;
                    $discount = $hasValidation ? min($baseFee, 5000 + 3000) : 0;
                    $totalFee = max(0, $baseFee - $discount);

                    $method = $totalFee === 0 ? ($hasValidation ? 'tenant_free' : 'member_free') : $methods[$idx % 2];

                    $batch[] = [
                        'uuid' => sprintf('%08x-%04x-4000-8000-%012x', $c, $r, $idx),
                        'ticket_number' => sprintf('TKT-%08d', $idx),
                        'property_id' => $property->id,
                        'parking_zone_id' => $zoneId,
                        'member_id' => null,
                        'vehicle_id' => null,
                        'plate_number' => sprintf('DA %04d %s', ($idx % 8999) + 1000, chr(65 + ($idx % 26)).chr(65 + (($idx + 3) % 26))),
                        'vehicle_type' => $isMotor ? 'motorcycle' : 'car',
                        'entry_gate' => $gates[$idx % 4],
                        'exit_gate' => $gates[($idx + 1) % 4],
                        'entry_time' => $entryTime,
                        'exit_time' => $exitTime,
                        'duration_minutes' => $durationMin,
                        'base_fee' => $baseFee,
                        'penalty_fee' => 0,
                        'discount_amount' => $discount,
                        'validated_by_tenant_id' => $hasValidation ? $sampleTenants[$idx % count($sampleTenants)] : null,
                        'validation_reference' => $hasValidation ? "VAL-{$idx}" : null,
                        'validation_free_hours' => $freeHours,
                        'validation_spend_amount' => $hasValidation ? 150000 : 0,
                        'validation_invoice_id' => null,
                        'total_fee' => $totalFee,
                        'payment_method' => $method,
                        'payment_status' => 'paid',
                        'paid_by_user_id' => null,
                        'paid_at' => $exitTime,
                        'is_lost_ticket' => 0,
                        'status' => 'completed',
                        'created_at' => $entryTime,
                        'updated_at' => $exitTime,
                    ];
                }

                DB::table('mall_parking_sessions')->insert($batch);

                if (($c + 1) % 50 === 0 || ($c + 1) === $chunksCount) {
                    $insertedSoFar = min(($c + 1) * $chunkSize, $targetCount);
                    $this->command?->line("    -> Tersimpan {$insertedSoFar} / {$targetCount} sesi parkir...");
                }
            }
        });
    }
}
