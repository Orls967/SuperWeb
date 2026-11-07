<?php

declare(strict_types=1);

namespace Modules\Hotel\database\seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;
use Modules\Hotel\Domain\Models\HtlSubFolio;

class HotelFase92CorporateAndMiceSeeder extends Seeder
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

        // 1. Akun Korporat
        $corp1 = HtlCorporateAccount::firstOrCreate(
            ['company_name' => 'PT Astra International Tbk'],
            [
                'tax_id_npwp' => '01.000.013.1-092.000',
                'billing_email' => 'finance.travel@astra.co.id',
                'credit_limit_idr' => 150000000,
                'current_outstanding_idr' => 38500000,
                'status' => 'ACTIVE',
            ]
        );

        $corp2 = HtlCorporateAccount::firstOrCreate(
            ['company_name' => 'PT Telkom Indonesia (Persero) Tbk'],
            [
                'tax_id_npwp' => '01.000.015.6-093.000',
                'billing_email' => 'corp.events@telkom.co.id',
                'credit_limit_idr' => 250000000,
                'current_outstanding_idr' => 84200000,
                'status' => 'ACTIVE',
            ]
        );

        $corp3 = HtlCorporateAccount::firstOrCreate(
            ['company_name' => 'GoTo Gojek Tokopedia'],
            [
                'tax_id_npwp' => '02.444.888.1-011.000',
                'billing_email' => 'accounting@goto.com',
                'credit_limit_idr' => 100000000,
                'current_outstanding_idr' => 12500000,
                'status' => 'ACTIVE',
            ]
        );

        // 2. Buat Folio dan Sub-Folio Contoh
        $folio = HotelFolio::firstOrCreate(
            ['booking_id' => 'BKG-CORP-9201'],
            [
                'property_id' => $property->id,
                'guest_id' => 'GST-CORP-EXEC-01',
                'balance_cents' => 1250000000,
                'status' => 'OPEN',
            ]
        );

        $subFolioCorp = HtlSubFolio::firstOrCreate(
            [
                'folio_id' => $folio->id,
                'payer_type' => 'CORPORATE',
                'corporate_id' => $corp1->id,
            ],
            [
                'total_charges_idr' => 10500000,
                'total_payments_idr' => 0,
                'balance_idr' => 10500000,
                'status' => 'OPEN',
            ]
        );

        $subFolioPersonal = HtlSubFolio::firstOrCreate(
            [
                'folio_id' => $folio->id,
                'payer_type' => 'PERSONAL',
                'corporate_id' => null,
            ],
            [
                'total_charges_idr' => 2000000,
                'total_payments_idr' => 0,
                'balance_idr' => 2000000,
                'status' => 'OPEN',
            ]
        );

        // Lines
        HotelFolioLine::firstOrCreate(
            [
                'folio_id' => $folio->id,
                'description' => 'Room Charge: Executive Suite (Direct Bill Astra)',
            ],
            [
                'line_type' => 'CHARGE',
                'charge_type' => 'ROOM',
                'amount_cents' => 1050000000,
                'is_taxable' => true,
                'business_date' => CarbonImmutable::now()->toDateString(),
                'metadata' => ['sub_folio_id' => $subFolioCorp->id],
            ]
        );

        HotelFolioLine::firstOrCreate(
            [
                'folio_id' => $folio->id,
                'description' => 'Minibar & Premium Spa (Personal Expense)',
            ],
            [
                'line_type' => 'CHARGE',
                'charge_type' => 'SPA',
                'amount_cents' => 200000000,
                'is_taxable' => true,
                'business_date' => CarbonImmutable::now()->toDateString(),
                'metadata' => ['sub_folio_id' => $subFolioPersonal->id],
            ]
        );
    }
}
