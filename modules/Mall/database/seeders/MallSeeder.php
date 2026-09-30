<?php

declare(strict_types=1);

namespace Modules\Mall\database\seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Mall\Application\Actions\GenerateMonthlyBillingAction;
use Modules\Mall\Application\Actions\PayInvoiceAction;
use Modules\Mall\Application\Actions\RecordUtilityReadingAction;
use Modules\Mall\Application\Actions\RegisterParkingMemberAction;
use Modules\Mall\Application\Services\FootfallGenerator;
use Modules\Mall\Application\Services\MallLedgerAccounts;
use Modules\Mall\Application\Services\TenantSalesService;
use Modules\Mall\Domain\Enums\AssetCategory;
use Modules\Mall\Domain\Enums\AssetStatus;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\EventBookingStatus;
use Modules\Mall\Domain\Enums\EventType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\LoyaltyTier;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Enums\UtilityType;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Mall\Domain\Enums\WorkOrderPriority;
use Modules\Mall\Domain\Enums\WorkOrderStatus;
use Modules\Mall\Domain\Enums\WorkOrderType;
use Modules\Mall\Domain\Models\Asset;
use Modules\Mall\Domain\Models\EventBooking;
use Modules\Mall\Domain\Models\EventSpace;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\ParkingMember;
use Modules\Mall\Domain\Models\ParkingTariff;
use Modules\Mall\Domain\Models\ParkingZone;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Modules\Mall\Domain\Models\UtilityTariff;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Modules\Mall\Domain\Models\WorkOrder;
use Modules\Mall\Domain\Models\Zone;

class MallSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Property: Duta Mall Banjarmasin
        $dutaMall = Property::updateOrCreate(
            ['code' => 'DM-BJM'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Duta Mall Banjarmasin',
                'address' => 'Jl. Ahmad Yani Km 2 No. 88, Kel. Melayu, Kec. Banjarmasin Tengah',
                'city' => 'Banjarmasin',
                'total_floors' => 5,
                'gla_sqm' => 55000.00,
                'is_active' => true,
            ]
        );

        // 2. Zones
        $zoneFood = Zone::updateOrCreate(
            ['property_id' => $dutaMall->id, 'name' => 'Food Court & Kuliner Nusantara'],
            [
                'uuid' => (string) Str::uuid(),
                'floor' => 'LG',
                'color_code' => '#f59e0b',
                'description' => 'Area ragam kuliner khas Minang, Banjar, dan kafe modern lantai bawah',
            ]
        );

        $zoneAuto = Zone::updateOrCreate(
            ['property_id' => $dutaMall->id, 'name' => 'Otomotif & Servis Kendaraan'],
            [
                'uuid' => (string) Str::uuid(),
                'floor' => 'LG',
                'color_code' => '#6366f1',
                'description' => 'Zona bengkel express, detailing cuci mobil, dan sparepart',
            ]
        );

        $zoneFashion = Zone::updateOrCreate(
            ['property_id' => $dutaMall->id, 'name' => 'Fashion & Lifestyle Boulevard'],
            [
                'uuid' => (string) Str::uuid(),
                'floor' => 'GF',
                'color_code' => '#3b82f6',
                'description' => 'Atrium utama dan butik ritel busana internasional',
            ]
        );

        $zoneGadget = Zone::updateOrCreate(
            ['property_id' => $dutaMall->id, 'name' => 'Elektronik & Gadget Hub'],
            [
                'uuid' => (string) Str::uuid(),
                'floor' => 'L1',
                'color_code' => '#10b981',
                'description' => 'Gerai resmi smartphone, laptop, dan perlengkapan audio',
            ]
        );

        $zoneCinema = Zone::updateOrCreate(
            ['property_id' => $dutaMall->id, 'name' => 'Bioskop & Entertainment'],
            [
                'uuid' => (string) Str::uuid(),
                'floor' => 'L2',
                'color_code' => '#8b5cf6',
                'description' => 'Bioskop XXI Premiere, game center arcade, dan karaoke',
            ]
        );

        // 3. Units
        $unitsData = [
            // Lower Ground (LG)
            ['num' => 'LG-01', 'floor' => 'LG', 'zone' => $zoneFood, 'area' => 85.0, 'base_rate' => 300000, 'sc' => 90000],
            ['num' => 'LG-02', 'floor' => 'LG', 'zone' => $zoneFood, 'area' => 60.0, 'base_rate' => 300000, 'sc' => 90000],
            ['num' => 'LG-08', 'floor' => 'LG', 'zone' => $zoneAuto, 'area' => 250.0, 'base_rate' => 200000, 'sc' => 70000], // AutoServe
            ['num' => 'LG-12', 'floor' => 'LG', 'zone' => $zoneFood, 'area' => 120.0, 'base_rate' => 350000, 'sc' => 95000], // RM Sari Ranah
            ['num' => 'LG-15', 'floor' => 'LG', 'zone' => $zoneFood, 'area' => 45.0, 'base_rate' => 320000, 'sc' => 90000],

            // Ground Floor (GF)
            ['num' => 'GF-01', 'floor' => 'GF', 'zone' => $zoneFashion, 'area' => 150.0, 'base_rate' => 500000, 'sc' => 120000], // Starbucks
            ['num' => 'GF-02', 'floor' => 'GF', 'zone' => $zoneFashion, 'area' => 400.0, 'base_rate' => 450000, 'sc' => 110000], // Uniqlo
            ['num' => 'GF-05', 'floor' => 'GF', 'zone' => $zoneFashion, 'area' => 90.0, 'base_rate' => 480000, 'sc' => 115000],
            ['num' => 'GF-10', 'floor' => 'GF', 'zone' => $zoneFashion, 'area' => 110.0, 'base_rate' => 470000, 'sc' => 110000],

            // Level 1 (L1)
            ['num' => 'L1-01', 'floor' => 'L1', 'zone' => $zoneGadget, 'area' => 130.0, 'base_rate' => 350000, 'sc' => 95000], // iBox
            ['num' => 'L1-05', 'floor' => 'L1', 'zone' => $zoneGadget, 'area' => 75.0, 'base_rate' => 340000, 'sc' => 90000],
            ['num' => 'L1-09', 'floor' => 'L1', 'zone' => $zoneGadget, 'area' => 80.0, 'base_rate' => 340000, 'sc' => 90000],

            // Level 2 (L2)
            ['num' => 'L2-01', 'floor' => 'L2', 'zone' => $zoneCinema, 'area' => 1200.0, 'base_rate' => 280000, 'sc' => 80000], // Cinema XXI
            ['num' => 'L2-05', 'floor' => 'L2', 'zone' => $zoneCinema, 'area' => 300.0, 'base_rate' => 260000, 'sc' => 75000], // Timezone
        ];

        $createdUnits = [];
        foreach ($unitsData as $ud) {
            $createdUnits[$ud['num']] = Unit::updateOrCreate(
                ['property_id' => $dutaMall->id, 'unit_number' => $ud['num']],
                [
                    'uuid' => (string) Str::uuid(),
                    'zone_id' => $ud['zone']->id,
                    'floor' => $ud['floor'],
                    'area_sqm' => $ud['area'],
                    'base_rent_rate_per_sqm' => $ud['base_rate'],
                    'service_charge_per_sqm' => $ud['sc'],
                    'status' => UnitStatus::AVAILABLE,
                    'coordinates' => ['x' => rand(10, 80), 'y' => rand(10, 80), 'w' => 15, 'h' => 15],
                ]
            );
        }

        // 4. Sample Tenants
        $adminUser = User::where('role', 'admin')->first();
        $customerUser = User::where('email', 'customer@autoserve.test')->first();

        $tSariRanah = Tenant::updateOrCreate(
            ['company_name' => 'PT Sari Ranah Minang'],
            [
                'uuid' => (string) Str::uuid(),
                'brand_name' => 'RM Sari Ranah',
                'category' => TenantCategory::FNB,
                'pic_name' => 'Datuk Maringgai',
                'pic_phone' => '081255566677',
                'pic_email' => 'leasing@sariranah.test',
                'npwp' => '01.234.567.8-731.000',
                // Referensi ke kode outlet Resto agar omzet POS & validasi parkir terhubung otomatis
                'external_ref' => 'DM-01',
                'user_id' => $adminUser?->id,
                'is_active' => true,
            ]
        );

        $tAutoServe = Tenant::updateOrCreate(
            ['company_name' => 'PT AutoServe Digital Nusantara'],
            [
                'uuid' => (string) Str::uuid(),
                'brand_name' => 'AutoServe Express Bengkel Mall',
                'category' => TenantCategory::AUTOMOTIVE,
                'pic_name' => 'Budi Santoso',
                'pic_phone' => '081234567890',
                'pic_email' => 'mall.bengkel@autoserve.test',
                'npwp' => '02.345.678.9-731.000',
                'external_ref' => 'AUTOSERVE-DM',
                'user_id' => $adminUser?->id,
                'is_active' => true,
            ]
        );

        $tStarbucks = Tenant::updateOrCreate(
            ['company_name' => 'PT Sari Coffee Indonesia'],
            [
                'uuid' => (string) Str::uuid(),
                'brand_name' => 'Starbucks Coffee',
                'category' => TenantCategory::FNB,
                'pic_name' => 'Jessica Tan',
                'pic_phone' => '081198765432',
                'pic_email' => 'leasing@starbucks.co.id',
                'npwp' => '01.111.222.3-054.000',
                'user_id' => $customerUser?->id,
                'is_active' => true,
            ]
        );

        $tXXI = Tenant::updateOrCreate(
            ['company_name' => 'PT Nusantara Sejahtera Raya'],
            [
                'uuid' => (string) Str::uuid(),
                'brand_name' => 'Cinema XXI & Premiere',
                'category' => TenantCategory::ENTERTAINMENT,
                'pic_name' => 'Agus Pratama',
                'pic_phone' => '081188899900',
                'pic_email' => 'property@21cineplex.com',
                'npwp' => '01.333.444.5-054.000',
                'user_id' => null,
                'is_active' => true,
            ]
        );

        // 5. Active Leases
        // Lease 1: RM Sari Ranah at Unit LG-12
        $unitLG12 = $createdUnits['LG-12'];
        $baseRentLG12 = $unitLG12->estimatedBaseRent();
        $scLG12 = $unitLG12->estimatedServiceCharge();

        $leaseSariRanah = Lease::updateOrCreate(
            ['lease_number' => 'LSE-DM-2026-001'],
            [
                'uuid' => (string) Str::uuid(),
                'property_id' => $dutaMall->id,
                'unit_id' => $unitLG12->id,
                'tenant_id' => $tSariRanah->id,
                'rent_model' => RentModel::GREATER_OF,
                'start_date' => Carbon::now()->subMonths(6)->toDateString(),
                'end_date' => Carbon::now()->addMonths(30)->toDateString(),
                'fit_out_days' => 30,
                'billing_day' => 1,
                'grace_days' => 7,
                'penalty_rate_daily_percent' => 0.10,
                'base_monthly_rent' => $baseRentLG12,
                'service_charge_monthly' => $scLG12,
                'annual_escalation_percent' => 5.00,
                'revenue_share_percent' => 8.00, // 8% dari omzet hidang
                // Tenant menanggung 2 jam parkir bila pelanggan belanja minimal Rp 100.000
                'parking_validation_hours' => 2,
                'parking_validation_min_spend' => 100000,
                'security_deposit_amount' => $baseRentLG12 * 3,
                'deposit_status' => DepositStatus::HELD,
                'status' => LeaseStatus::ACTIVE,
                'activated_at' => Carbon::now()->subMonths(6),
            ]
        );
        $unitLG12->update(['status' => UnitStatus::LEASED]);

        // Lease 2: AutoServe Express at Unit LG-08
        $unitLG08 = $createdUnits['LG-08'];
        $baseRentLG08 = $unitLG08->estimatedBaseRent();
        $scLG08 = $unitLG08->estimatedServiceCharge();

        $leaseAutoServe = Lease::updateOrCreate(
            ['lease_number' => 'LSE-DM-2026-002'],
            [
                'uuid' => (string) Str::uuid(),
                'property_id' => $dutaMall->id,
                'unit_id' => $unitLG08->id,
                'tenant_id' => $tAutoServe->id,
                'rent_model' => RentModel::FIXED,
                'start_date' => Carbon::now()->subMonths(4)->toDateString(),
                'end_date' => Carbon::now()->addMonths(20)->toDateString(),
                'fit_out_days' => 45,
                'billing_day' => 5,
                'grace_days' => 7,
                'penalty_rate_daily_percent' => 0.10,
                'base_monthly_rent' => $baseRentLG08,
                'service_charge_monthly' => $scLG08,
                'annual_escalation_percent' => 5.00,
                'revenue_share_percent' => null,
                // Servis kendaraan berdurasi lama: 3 jam parkir ditanggung bengkel
                'parking_validation_hours' => 3,
                'parking_validation_min_spend' => 250000,
                'security_deposit_amount' => $baseRentLG08 * 3,
                'deposit_status' => DepositStatus::HELD,
                'status' => LeaseStatus::ACTIVE,
                'activated_at' => Carbon::now()->subMonths(4),
            ]
        );
        $unitLG08->update(['status' => UnitStatus::LEASED]);

        // Lease 3: Starbucks at GF-01
        $unitGF01 = $createdUnits['GF-01'];
        $baseRentGF01 = $unitGF01->estimatedBaseRent();
        $scGF01 = $unitGF01->estimatedServiceCharge();

        $leaseStarbucks = Lease::updateOrCreate(
            ['lease_number' => 'LSE-DM-2026-003'],
            [
                'uuid' => (string) Str::uuid(),
                'property_id' => $dutaMall->id,
                'unit_id' => $unitGF01->id,
                'tenant_id' => $tStarbucks->id,
                'rent_model' => RentModel::REVENUE_SHARE,
                'start_date' => Carbon::now()->subMonths(2)->toDateString(),
                'end_date' => Carbon::now()->addDays(60)->toDateString(), // Expiring soon (<90 days)
                'fit_out_days' => 30,
                'billing_day' => 1,
                'grace_days' => 7,
                'penalty_rate_daily_percent' => 0.10,
                'base_monthly_rent' => $baseRentGF01,
                'service_charge_monthly' => $scGF01,
                'annual_escalation_percent' => 7.00,
                'revenue_share_percent' => 12.00,
                'parking_validation_hours' => 1,
                'parking_validation_min_spend' => 75000,
                'security_deposit_amount' => $baseRentGF01 * 3,
                'deposit_status' => DepositStatus::HELD,
                'status' => LeaseStatus::ACTIVE,
                'activated_at' => Carbon::now()->subMonths(2),
            ]
        );
        $unitGF01->update(['status' => UnitStatus::LEASED]);

        // 6. Utility Tariffs for Duta Mall
        // Listrik Berjenjang
        UtilityTariff::updateOrCreate(
            ['property_id' => $dutaMall->id, 'utility_type' => UtilityType::ELECTRICITY, 'tier_number' => 1],
            ['tier_min' => 0.0, 'tier_max' => 500.0, 'rate_per_unit' => 1500, 'standing_charge' => 100000, 'effective_from' => '2026-01-01']
        );
        UtilityTariff::updateOrCreate(
            ['property_id' => $dutaMall->id, 'utility_type' => UtilityType::ELECTRICITY, 'tier_number' => 2],
            ['tier_min' => 500.0, 'tier_max' => 2000.0, 'rate_per_unit' => 1800, 'standing_charge' => 0, 'effective_from' => '2026-01-01']
        );
        UtilityTariff::updateOrCreate(
            ['property_id' => $dutaMall->id, 'utility_type' => UtilityType::ELECTRICITY, 'tier_number' => 3],
            ['tier_min' => 2000.0, 'tier_max' => null, 'rate_per_unit' => 2200, 'standing_charge' => 0, 'effective_from' => '2026-01-01']
        );

        // Air PDAM Berjenjang
        UtilityTariff::updateOrCreate(
            ['property_id' => $dutaMall->id, 'utility_type' => UtilityType::WATER, 'tier_number' => 1],
            ['tier_min' => 0.0, 'tier_max' => 50.0, 'rate_per_unit' => 8000, 'standing_charge' => 50000, 'effective_from' => '2026-01-01']
        );
        UtilityTariff::updateOrCreate(
            ['property_id' => $dutaMall->id, 'utility_type' => UtilityType::WATER, 'tier_number' => 2],
            ['tier_min' => 50.0, 'tier_max' => null, 'rate_per_unit' => 12000, 'standing_charge' => 0, 'effective_from' => '2026-01-01']
        );

        // 7. Ensure All Mall Ledger Accounts Exist
        $mallAccounts = [
            'liability:mall:tenant_deposit:IDR' => ['name' => 'Uang Jaminan Tenant Mall (Deposit)', 'kind' => AccountKind::LIABILITY, 'allow_negative' => true],
            'revenue:mall:rent:IDR' => ['name' => 'Pendapatan Sewa & Bagi Hasil Mall', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
            'revenue:mall:service_charge:IDR' => ['name' => 'Pendapatan Service Charge Mall', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
            'revenue:mall:utilities:electricity:IDR' => ['name' => 'Pendapatan Utilitas Listrik Mall', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
            'revenue:mall:utilities:water:IDR' => ['name' => 'Pendapatan Utilitas Air Bersih Mall', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
            'revenue:mall:utilities:ac_overtime:IDR' => ['name' => 'Pendapatan Lembur AC Mall', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
            'revenue:mall:penalties:IDR' => ['name' => 'Pendapatan Denda Keterlambatan Mall', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
            'revenue:mall:rent_settlement:IDR' => ['name' => 'Pendapatan Penyelesaian Sewa Mall', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
        ];

        foreach ($mallAccounts as $code => $attr) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $attr['name'],
                    'asset_code' => 'IDR',
                    'kind' => $attr['kind'],
                    'allow_negative' => $attr['allow_negative'],
                ]
            );
        }

        // 8. Seed Sales Reports & Utility Readings for Current Month
        $currentMonth = date('Y-m');

        // Laporan omzet Sari Ranah (Rp 550.000.000)
        app(TenantSalesService::class)->recordManualSales(
            lease: $leaseSariRanah,
            periodMonth: $currentMonth,
            grossSales: 600000000,
            netSales: 550000000,
            txCount: 4200,
            notes: 'Realisasi omzet bulanan RM Sari Ranah Cabang Duta Mall'
        );

        // Laporan omzet Starbucks (Rp 250.000.000)
        app(TenantSalesService::class)->recordManualSales(
            lease: $leaseStarbucks,
            periodMonth: $currentMonth,
            grossSales: 260000000,
            netSales: 250000000,
            txCount: 5100,
            notes: 'Realisasi omzet Starbucks GF-01'
        );

        // Utility Readings for active leases
        $readingAction = app(RecordUtilityReadingAction::class);
        $readingAction->execute($leaseSariRanah, $currentMonth, UtilityType::ELECTRICITY, 3500.0, 2000.0); // 1500 kWh
        $readingAction->execute($leaseSariRanah, $currentMonth, UtilityType::WATER, 120.0, 40.0); // 80 m3

        $readingAction->execute($leaseAutoServe, $currentMonth, UtilityType::ELECTRICITY, 2800.0, 1600.0); // 1200 kWh
        $readingAction->execute($leaseAutoServe, $currentMonth, UtilityType::WATER, 65.0, 20.0); // 45 m3

        $readingAction->execute($leaseStarbucks, $currentMonth, UtilityType::ELECTRICITY, 4200.0, 2600.0); // 1600 kWh
        $readingAction->execute($leaseStarbucks, $currentMonth, UtilityType::WATER, 150.0, 80.0); // 70 m3

        // 9. Generate Monthly Invoices via GenerateMonthlyBillingAction & Pay partial invoice
        app(GenerateMonthlyBillingAction::class)->generateAll($currentMonth, $dutaMall->id);

        $starbucksInvoice = Invoice::where('tenant_id', $tStarbucks->id)
            ->where('status', InvoiceStatus::ISSUED)
            ->first();

        if ($starbucksInvoice && $customerUser) {
            $topUp = app(TopUpAction::class);
            $setPin = app(SetPinAction::class);
            $payInvoice = app(PayInvoiceAction::class);

            $payAmount = 20_000_000;
            $customerUser->walletAccount('IDR');
            $setPin->execute($customerUser, '123456');
            $topUp->execute($customerUser, $payAmount + 5_000_000, "seed_mall_pay_starbucks_{$starbucksInvoice->id}");
            $payInvoice->execute($starbucksInvoice, $payAmount, '123456', $customerUser);
        }

        // 10. Parkir: zona, tarif progresif, akun ledger, dan langganan member
        $this->seedParking($dutaMall, $customerUser);

        // 11. Data kunjungan 30 hari terakhir untuk analitik footfall
        app(FootfallGenerator::class)->generate(
            property: $dutaMall,
            startDate: Carbon::today()->subDays(29),
            endDate: Carbon::today(),
        );

        // 12. FASE 15: Loyalty, Voucher, Event Atrium & Fasilitas Gedung
        $this->seedLoyaltyAndFacilities($dutaMall, $customerUser, $tSariRanah, $createdUnits['LG-12'] ?? null);
    }

    /**
     * Zona parkir, tarif, akun ledger parkir, dan satu langganan member contoh.
     */
    private function seedParking(Property $property, ?User $customerUser): void
    {
        // Akun sistem ledger parkir & keanggotaan
        app(MallLedgerAccounts::class)->ensureAll();

        $zones = [
            ['code' => 'P1-MOBIL', 'name' => 'Basement 1 — Mobil', 'vehicle_type' => VehicleType::CAR, 'total_capacity' => 320],
            ['code' => 'P2-MOBIL', 'name' => 'Basement 2 — Mobil', 'vehicle_type' => VehicleType::CAR, 'total_capacity' => 280],
            ['code' => 'P3-MOTOR', 'name' => 'Ground — Sepeda Motor', 'vehicle_type' => VehicleType::MOTORCYCLE, 'total_capacity' => 600],
        ];

        foreach ($zones as $zone) {
            ParkingZone::updateOrCreate(
                ['property_id' => $property->id, 'code' => $zone['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $zone['name'],
                    'vehicle_type' => $zone['vehicle_type'],
                    'total_capacity' => $zone['total_capacity'],
                    'current_occupancy' => 0,
                    'is_active' => true,
                ]
            );
        }

        // Tarif progresif: jam pertama, jam berikutnya, batas harian, denda tiket hilang
        $tariffs = [
            [
                'vehicle_type' => VehicleType::CAR,
                'grace_period_minutes' => 15,
                'first_hour_rate' => 5000,
                'subsequent_hour_rate' => 3000,
                'max_daily_rate' => 30000,
                'lost_ticket_penalty' => 50000,
            ],
            [
                'vehicle_type' => VehicleType::MOTORCYCLE,
                'grace_period_minutes' => 15,
                'first_hour_rate' => 3000,
                'subsequent_hour_rate' => 1000,
                'max_daily_rate' => 12000,
                'lost_ticket_penalty' => 25000,
            ],
            [
                'vehicle_type' => VehicleType::TRUCK,
                'grace_period_minutes' => 10,
                'first_hour_rate' => 10000,
                'subsequent_hour_rate' => 6000,
                'max_daily_rate' => 60000,
                'lost_ticket_penalty' => 100000,
            ],
        ];

        foreach ($tariffs as $tariff) {
            ParkingTariff::updateOrCreate(
                ['property_id' => $property->id, 'vehicle_type' => $tariff['vehicle_type']],
                $tariff + ['is_active' => true]
            );
        }

        // Langganan member contoh yang terhubung ke kendaraan di My Garage
        $vehicle = $customerUser !== null
            ? Vehicle::where('user_id', $customerUser->id)->whereNotNull('plate_number')->first()
            : null;

        if ($vehicle !== null) {
            ParkingMember::updateOrCreate(
                [
                    'property_id' => $property->id,
                    'plate_number' => strtoupper((string) $vehicle->plate_number),
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $customerUser->id,
                    'vehicle_id' => $vehicle->id,
                    'member_number' => 'MBR-DEMO001',
                    'vehicle_type' => VehicleType::CAR,
                    'monthly_price' => RegisterParkingMemberAction::DEFAULT_MONTHLY_PRICE,
                    'auto_renew' => true,
                    'start_date' => Carbon::today()->subDays(10),
                    'end_date' => Carbon::today()->addDays(20),
                    'status' => MemberStatus::ACTIVE,
                    'notes' => 'Langganan demo bebas parkir Duta Mall',
                ]
            );
        }
    }

    protected function seedLoyaltyAndFacilities(
        Property $property,
        ?User $customerUser,
        Tenant $tenantSariRanah,
        ?Unit $unitSariRanah
    ): void {
        // 1. Voucher Templates
        $templates = [
            [
                'code' => 'VCH-25K',
                'title' => 'Voucher Diskon Belanja Rp 25.000',
                'description' => 'Potongan langsung Rp 25.000 dengan minimal belanja Rp 100.000 di seluruh tenant Duta Mall.',
                'points_required' => 25,
                'nominal_value' => 25000,
                'min_spend' => 100000,
                'validity_days' => 30,
            ],
            [
                'code' => 'VCH-50K',
                'title' => 'Voucher Diskon Belanja Rp 50.000',
                'description' => 'Potongan langsung Rp 50.000 dengan minimal belanja Rp 200.000 di seluruh tenant Duta Mall.',
                'points_required' => 50,
                'nominal_value' => 50000,
                'min_spend' => 200000,
                'validity_days' => 30,
            ],
            [
                'code' => 'VCH-100K',
                'title' => 'Voucher Belanja Sultan Rp 100.000',
                'description' => 'Voucher eksklusif potongan Rp 100.000 dengan minimal belanja Rp 500.000.',
                'points_required' => 100,
                'nominal_value' => 100000,
                'min_spend' => 500000,
                'validity_days' => 45,
            ],
        ];

        foreach ($templates as $t) {
            VoucherTemplate::updateOrCreate(
                ['code' => $t['code']],
                $t + ['uuid' => (string) Str::uuid(), 'is_active' => true]
            );
        }

        // 2. Profil Loyalty Member Demo Customer
        if ($customerUser !== null) {
            LoyaltyMember::updateOrCreate(
                ['user_id' => $customerUser->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'tier' => LoyaltyTier::GOLD,
                    'lifetime_spend' => 15000000,
                    'current_year_spend' => 15000000,
                    'tier_expires_at' => Carbon::now()->addYear(),
                ]
            );
        }

        // 3. Event Spaces & Atrium
        $spaces = [
            [
                'code' => 'ATR-MAIN',
                'name' => 'Atrium Utama Ground Floor',
                'area_sqm' => 450.0,
                'daily_rate' => 15000000,
                'hourly_rate' => 2000000,
                'max_booths' => 20,
                'description' => 'Area pameran utama di tengah mall dengan akses visual dari semua lantai.',
            ],
            [
                'code' => 'ATR-NORTH',
                'name' => 'North Corridor Exhibition Hall',
                'area_sqm' => 200.0,
                'daily_rate' => 7500000,
                'hourly_rate' => 1000000,
                'max_booths' => 10,
                'description' => 'Koridor pameran utara ideal untuk bazaar fashion dan pameran otomotif mini.',
            ],
            [
                'code' => 'ATR-ROOFTOP',
                'name' => 'Sky Atrium Rooftop 4F',
                'area_sqm' => 350.0,
                'daily_rate' => 10000000,
                'hourly_rate' => 1500000,
                'max_booths' => 15,
                'description' => 'Area rooftop terbuka untuk festival musik, konser, dan culinary night bazaar.',
            ],
        ];

        $createdSpaces = [];
        foreach ($spaces as $s) {
            $createdSpaces[$s['code']] = EventSpace::updateOrCreate(
                ['property_id' => $property->id, 'code' => $s['code']],
                $s + ['uuid' => (string) Str::uuid(), 'is_active' => true]
            );
        }

        // 4. Sample Event Booking
        if (isset($createdSpaces['ATR-MAIN']) && $customerUser !== null) {
            EventBooking::updateOrCreate(
                ['booking_number' => 'EVT-202610-001'],
                [
                    'uuid' => (string) Str::uuid(),
                    'property_id' => $property->id,
                    'event_space_id' => $createdSpaces['ATR-MAIN']->id,
                    'customer_id' => $customerUser->id,
                    'event_name' => 'Duta Mall Culinary & Craft Expo 2026',
                    'event_type' => EventType::BAZAAR,
                    'start_date' => Carbon::now()->addDays(5)->toDateString(),
                    'end_date' => Carbon::now()->addDays(8)->toDateString(),
                    'booth_count' => 15,
                    'total_amount' => 60000000,
                    'paid_amount' => 60000000,
                    'status' => EventBookingStatus::CONFIRMED,
                    'confirmed_at' => Carbon::now(),
                    'notes' => 'Pameran bazaar kuliner nusantara',
                ]
            );
        }

        // 5. Assets Fasilitas Gedung
        $assets = [
            [
                'asset_tag' => 'AST-HVAC-01',
                'name' => 'Water Chiller Central Carrier 500TR',
                'category' => AssetCategory::HVAC,
                'brand' => 'Carrier',
                'model_number' => '19XRV-500',
                'pm_frequency_days' => 30,
                'status' => AssetStatus::OPERATIONAL,
                'last_pm_date' => Carbon::today()->subDays(31)->toDateString(),
                'next_pm_date' => Carbon::today()->subDay()->toDateString(), // Jatuh tempo PM!
            ],
            [
                'asset_tag' => 'AST-LIFT-01',
                'name' => 'Passenger Elevator Otis 15-Pax Central',
                'category' => AssetCategory::ELEVATOR,
                'brand' => 'Otis',
                'model_number' => 'Gen2-Regen',
                'pm_frequency_days' => 15,
                'status' => AssetStatus::OPERATIONAL,
                'last_pm_date' => Carbon::today()->subDays(5)->toDateString(),
                'next_pm_date' => Carbon::today()->addDays(10)->toDateString(),
            ],
            [
                'asset_tag' => 'AST-GEN-01',
                'name' => 'Cummins Diesel Backup Generator 500kVA',
                'category' => AssetCategory::ELECTRICAL,
                'brand' => 'Cummins',
                'model_number' => 'QSK19-G4',
                'pm_frequency_days' => 60,
                'status' => AssetStatus::OPERATIONAL,
                'last_pm_date' => Carbon::today()->subDays(10)->toDateString(),
                'next_pm_date' => Carbon::today()->addDays(50)->toDateString(),
            ],
            [
                'asset_tag' => 'AST-PUMP-01',
                'name' => 'Grundfos Clean Water Booster Pump LG',
                'category' => AssetCategory::PLUMBING,
                'brand' => 'Grundfos',
                'model_number' => 'Hydro-MPC',
                'pm_frequency_days' => 45,
                'status' => AssetStatus::MAINTENANCE,
                'last_pm_date' => Carbon::today()->subDays(50)->toDateString(),
                'next_pm_date' => Carbon::today()->subDays(5)->toDateString(),
            ],
        ];

        $createdAssets = [];
        foreach ($assets as $a) {
            $createdAssets[$a['asset_tag']] = Asset::updateOrCreate(
                ['property_id' => $property->id, 'asset_tag' => $a['asset_tag']],
                $a + ['uuid' => (string) Str::uuid()]
            );
        }

        // 6. Work Orders (SPK)
        if (isset($createdAssets['AST-HVAC-01'])) {
            WorkOrder::updateOrCreate(
                ['order_number' => 'WO-202609-001'],
                [
                    'uuid' => (string) Str::uuid(),
                    'property_id' => $property->id,
                    'asset_id' => $createdAssets['AST-HVAC-01']->id,
                    'order_number' => 'WO-202609-001',
                    'type' => WorkOrderType::PREVENTIVE,
                    'priority' => WorkOrderPriority::MEDIUM,
                    'title' => 'Inspeksi & Pembersihan Filter Chiller HVAC',
                    'description' => 'Pembersihan kondensor dan filter oli rutin bulanan.',
                    'status' => WorkOrderStatus::COMPLETED,
                    'due_date' => Carbon::now()->subDays(2),
                    'completed_at' => Carbon::now()->subDays(2),
                    'parts_cost' => 500000,
                    'labor_cost' => 300000,
                    'total_cost' => 800000,
                    'is_billable_to_tenant' => false,
                ]
            );
        }

        if ($unitSariRanah !== null) {
            WorkOrder::updateOrCreate(
                ['order_number' => 'WO-202609-002'],
                [
                    'uuid' => (string) Str::uuid(),
                    'property_id' => $property->id,
                    'unit_id' => $unitSariRanah->id,
                    'tenant_id' => $tenantSariRanah->id,
                    'order_number' => 'WO-202609-002',
                    'type' => WorkOrderType::CORRECTIVE,
                    'priority' => WorkOrderPriority::HIGH,
                    'title' => 'Perbaikan Pipa Pembuangan Grease Trap Resto',
                    'description' => 'Pembersihan sumbatan lemak dapur hidang dan perbaikan seal pipa.',
                    'status' => WorkOrderStatus::OPEN,
                    'due_date' => Carbon::now()->addHours(8),
                    'sla_hours' => 8,
                    'parts_cost' => 250000,
                    'labor_cost' => 200000,
                    'total_cost' => 450000,
                    'is_billable_to_tenant' => true,
                ]
            );
        }
    }
}
