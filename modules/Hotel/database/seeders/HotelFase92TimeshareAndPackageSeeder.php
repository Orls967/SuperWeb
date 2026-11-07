<?php

declare(strict_types=1);

namespace Modules\Hotel\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Hotel\Domain\Models\DestinationPackage;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HtlDestinationPackageAllocation;
use Modules\Hotel\Domain\Models\HtlDestinationPackageBooking;
use Modules\Hotel\Domain\Models\HtlTimeshareSchedule;
use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;

class HotelFase92TimeshareAndPackageSeeder extends Seeder
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

        // 1. Timeshare Villa Units
        $villa = TimeshareUnit::firstOrCreate(
            ['unit_code' => 'TS-VILLA-01'],
            [
                'property_id' => $property->id,
                'unit_name' => 'Royal Beachfront Pool Villa 01',
                'total_token_shares' => 100,
                'daily_rental_rate_idr' => 7500000,
            ]
        );

        // Investor Shares
        TimeshareInvestor::firstOrCreate(
            [
                'timeshare_unit_id' => $villa->id,
                'investor_user_id' => '00000000-0000-0000-0000-000000000001',
            ],
            [
                'token_shares' => 60,
            ]
        );

        TimeshareInvestor::firstOrCreate(
            [
                'timeshare_unit_id' => $villa->id,
                'investor_user_id' => '00000000-0000-0000-0000-000000000002',
            ],
            [
                'token_shares' => 40,
            ]
        );

        // Jadwal Penggunaan
        HtlTimeshareSchedule::firstOrCreate(
            [
                'timeshare_unit_id' => $villa->id,
                'week_number' => 28,
            ],
            [
                'owner_reference' => '00000000-0000-0000-0000-000000000001',
                'season' => 'PEAK',
                'status' => 'RENTAL_POOL',
                'rental_income_gross_idr' => 45000000,
                'dividend_net_idr' => 38250000,
            ]
        );

        HtlTimeshareSchedule::firstOrCreate(
            [
                'timeshare_unit_id' => $villa->id,
                'week_number' => 32,
            ],
            [
                'owner_reference' => '00000000-0000-0000-0000-000000000002',
                'season' => 'REGULAR',
                'status' => 'OWNER_OCCUPIED',
                'rental_income_gross_idr' => 0,
                'dividend_net_idr' => 0,
            ]
        );

        // 2. Paket Destinasi Multi-Vendor
        $pkg = DestinationPackage::firstOrCreate(
            ['package_code' => 'PKG-BALI-3D2N-VIP'],
            [
                'title' => 'Bali Luxury Escape 3D2N (Resort + Uluwatu Show + Transport)',
                'total_price_idr' => 12500000,
                'vendor_shares' => [
                    ['vendor' => 'HOTEL', 'amount' => 7500000, 'account' => 'hotel:escrow:pms:IDR'],
                    ['vendor' => 'VENUE', 'amount' => 3000000, 'account' => 'venue:escrow:ticket:IDR'],
                    ['vendor' => 'LOGISTICS', 'amount' => 2000000, 'account' => 'logistics:escrow:transfer:IDR'],
                ],
            ]
        );

        $booking = HtlDestinationPackageBooking::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000009299'],
            [
                'destination_package_id' => $pkg->id,
                'guest_reference' => 'GST-DEST-001',
                'total_paid_idr' => 12500000,
                'escrow_held_idr' => 5000000,
                'escrow_status' => 'PARTIALLY_RELEASED',
            ]
        );

        HtlDestinationPackageAllocation::firstOrCreate(
            [
                'package_booking_id' => $booking->id,
                'vendor_type' => 'HOTEL',
            ],
            [
                'vendor_reference' => 'HTL-ROOM-STAY-3D2N',
                'ssp_amount_idr' => 7500000,
                'release_status' => 'RELEASED',
                'released_at' => now(),
            ]
        );

        HtlDestinationPackageAllocation::firstOrCreate(
            [
                'package_booking_id' => $booking->id,
                'vendor_type' => 'VENUE',
            ],
            [
                'vendor_reference' => 'VENUE-TICKET-VIP-ULUWATU',
                'ssp_amount_idr' => 3000000,
                'release_status' => 'PENDING',
                'released_at' => null,
            ]
        );

        HtlDestinationPackageAllocation::firstOrCreate(
            [
                'package_booking_id' => $booking->id,
                'vendor_type' => 'LOGISTICS',
            ],
            [
                'vendor_reference' => 'TRP-ALPHARD-TRANSFER',
                'ssp_amount_idr' => 2000000,
                'release_status' => 'PENDING',
                'released_at' => null,
            ]
        );
    }
}
