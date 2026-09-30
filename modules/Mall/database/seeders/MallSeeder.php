<?php

declare(strict_types=1);

namespace Modules\Mall\database\seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
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
                'security_deposit_amount' => $baseRentGF01 * 3,
                'deposit_status' => DepositStatus::HELD,
                'status' => LeaseStatus::ACTIVE,
                'activated_at' => Carbon::now()->subMonths(2),
            ]
        );
        $unitGF01->update(['status' => UnitStatus::LEASED]);
    }
}
