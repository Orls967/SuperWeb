<?php

declare(strict_types=1);

namespace Modules\Hotel\database\seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\TimeshareUnit;

class HotelFase92ComprehensiveEnterpriseSeeder extends Seeder
{
    public function run(): void
    {
        $property = HotelProperty::firstOrCreate(
            ['code' => 'HTL-BALI-01'],
            [
                'name' => 'Nusantara Grand Resort & Spa Bali',
                'description' => 'Luxury resort on beachfront Nusa Dua',
                'is_active' => true,
            ]
        );

        // 1. Tambah Akun Korporat Enterprise
        $corps = [
            [
                'company_name' => 'PT Bank Rakyat Indonesia (Persero) Tbk',
                'tax_id_npwp' => '01.000.018.0-091.000',
                'billing_email' => 'mice.travel@bri.co.id',
                'credit_limit_idr' => 500000000,
                'current_outstanding_idr' => 125000000,
            ],
            [
                'company_name' => 'PT Bank Central Asia Tbk',
                'tax_id_npwp' => '01.000.021.4-092.000',
                'billing_email' => 'corp.sec@bca.co.id',
                'credit_limit_idr' => 400000000,
                'current_outstanding_idr' => 64000000,
            ],
            [
                'company_name' => 'PT Pertamina (Persero)',
                'tax_id_npwp' => '01.000.033.9-093.000',
                'billing_email' => 'travel.desk@pertamina.com',
                'credit_limit_idr' => 600000000,
                'current_outstanding_idr' => 210000000,
            ],
        ];

        foreach ($corps as $c) {
            HtlCorporateAccount::firstOrCreate(
                ['company_name' => $c['company_name']],
                [
                    'tax_id_npwp' => $c['tax_id_npwp'],
                    'billing_email' => $c['billing_email'],
                    'credit_limit_idr' => $c['credit_limit_idr'],
                    'current_outstanding_idr' => $c['current_outstanding_idr'],
                    'status' => 'ACTIVE',
                ]
            );
        }

        // 2. Kontrak Jamuan MICE Berskala Besar
        MiceContract::firstOrCreate(
            ['contract_number' => 'MICE-2026-BRI-GALA'],
            [
                'property_id' => $property->id,
                'client_name' => 'PT Bank Rakyat Indonesia (Persero) Tbk',
                'event_name' => 'BRI National Leadership Summit & Annual Gala Dinner 2026',
                'event_date' => CarbonImmutable::now()->addDays(14)->toDateString(),
                'pax_count' => 450,
                'contract_value_idr' => 225000000,
                'deposit_paid_idr' => 112500000,
                'status' => 'CHEF_CONFIRMED',
            ]
        );

        MiceContract::firstOrCreate(
            ['contract_number' => 'MICE-2026-WEDDING-ROYAL'],
            [
                'property_id' => $property->id,
                'client_name' => 'Keluarga Bpk. Hendrawan Santoso',
                'event_name' => 'Royal Beachfront Wedding Reception',
                'event_date' => CarbonImmutable::now()->addDays(28)->toDateString(),
                'pax_count' => 300,
                'contract_value_idr' => 180000000,
                'deposit_paid_idr' => 90000000,
                'status' => 'SALES_CONFIRMED',
            ]
        );

        // 3. Portofolio Timeshare Unit Tambahan
        TimeshareUnit::firstOrCreate(
            ['unit_code' => 'TS-VILLA-02'],
            [
                'property_id' => $property->id,
                'unit_name' => 'Sunset Cliff Oceanfront Villa 02',
                'total_token_shares' => 100,
                'daily_rental_rate_idr' => 8500000,
            ]
        );

        TimeshareUnit::firstOrCreate(
            ['unit_code' => 'TS-PENTHOUSE-01'],
            [
                'property_id' => $property->id,
                'unit_name' => 'Royal Presidential Penthouse Nusa Dua',
                'total_token_shares' => 100,
                'daily_rental_rate_idr' => 15000000,
            ]
        );
    }
}
