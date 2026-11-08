<?php

declare(strict_types=1);

namespace Modules\Hotel\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\HtlEventProposal;
use Modules\Hotel\Domain\Models\HtlExhibitionBoothReservation;
use Modules\Hotel\Domain\Models\HtlMiceLead;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;

final class HotelMiceBanquetSalesSeeder extends Seeder
{
    public function run(): void
    {
        $property = HotelProperty::firstOrCreate(
            ['property_code' => 'HTL-BALI-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Grand Hyatt Nusa Dua Resort & Convention Center',
                'property_type' => 'RESORT',
                'city' => 'Badung',
                'total_rooms' => 450,
            ]
        );

        // 1. Leads
        $lead1 = HtlMiceLead::firstOrCreate(
            ['lead_code' => 'LEAD-CONF-2026-01'],
            [
                'id' => (string) Str::uuid(),
                'organizer_name' => 'Dr. Ir. Hendra Gunawan',
                'company_name' => 'Kementerian Koordinator Bidang Kemaritiman & Investasi',
                'email' => 'hendra.gunawan@maritim.go.id',
                'phone' => '08119876123',
                'event_type' => 'CONFERENCE',
                'expected_pax' => 600,
                'estimated_budget_idr' => 750000000,
                'tentative_date' => '2026-11-20',
                'status' => 'QUALIFIED',
                'created_by_reference' => 'USR-SALES-01',
            ]
        );

        $lead2 = HtlMiceLead::firstOrCreate(
            ['lead_code' => 'LEAD-WED-2026-02'],
            [
                'id' => (string) Str::uuid(),
                'organizer_name' => 'Natasha Wilona & Kevin Sanjaya',
                'company_name' => 'Citra Wedding Organizer',
                'email' => 'natasha@citrawedding.com',
                'phone' => '081288990011',
                'event_type' => 'WEDDING',
                'expected_pax' => 500,
                'estimated_budget_idr' => 285000000,
                'tentative_date' => '2027-06-15',
                'status' => 'QUALIFIED',
                'created_by_reference' => 'USR-SALES-01',
            ]
        );

        // 2. Proposal
        $prop1 = HtlEventProposal::firstOrCreate(
            ['proposal_code' => 'PROP-CONF-2026-001'],
            [
                'id' => (string) Str::uuid(),
                'lead_id' => $lead1->id,
                'title' => 'ASEAN Maritime Investment & Blue Economy Summit 2026',
                'hall_rental_price_idr' => 120000000,
                'fnb_price_idr' => 280000000,
                'av_price_idr' => 60000000,
                'room_price_idr' => 200000000,
                'booth_price_idr' => 90000000,
                'other_price_idr' => 0,
                'total_revenue_idr' => 750000000,
                'food_cogs_idr' => 120000000,
                'bev_cogs_idr' => 45000000,
                'labor_cost_idr' => 30000000,
                'av_cost_idr' => 50000000,
                'utility_cost_idr' => 25000000,
                'other_cost_idr' => 10000000,
                'total_direct_cost_idr' => 280000000,
                'gross_margin_percent' => 62.67,
                'status' => 'CONVERTED',
                'timelock_expires_at' => now()->addDays(14),
                'signature_hash' => hash('sha256', 'PROP-CONF-2026-001-SALT'),
                'created_by_reference' => 'USR-SALES-01',
            ]
        );

        // 3. Contract
        $contract1 = MiceContract::firstOrCreate(
            ['event_code' => 'CTR-MICE-2026-8812'],
            [
                'id' => (string) Str::uuid(),
                'proposal_id' => $prop1->id,
                'property_id' => $property->id,
                'event_type' => 'CONFERENCE',
                'client_name' => $lead1->company_name,
                'event_date' => '2026-11-20',
                'signing_date' => '2026-11-08',
                'event_start_date' => '2026-11-20',
                'event_end_date' => '2026-11-23',
                'contract_total_value_idr' => 750000000,
                'actual_banquet_cost_idr' => 280000000,
                'current_milestone_index' => 1,
                'status' => 'CONFIRMED',
                'is_bridal_suite_complimentary' => false,
                'signature_hash' => hash('sha256', 'CTR-MICE-2026-8812-SALT'),
                'created_by_reference' => 'USR-SALES-01',
            ]
        );

        // 4. Room Block Allotment
        RoomBlockAllotment::firstOrCreate(
            ['block_code' => 'BLK-MICE-2026-001'],
            [
                'id' => (string) Str::uuid(),
                'mice_contract_id' => $contract1->id,
                'check_in_date' => '2026-11-20',
                'check_out_date' => '2026-11-23',
                'release_deadline_date' => '2026-10-21',
                'allotted_rooms_count' => 200,
                'confirmed_rooms_count' => 110,
                'released_rooms_count' => 35,
                'daily_rate_idr' => 1000000,
                'status' => 'HELD',
                'h30_cutoff_date' => '2026-10-21',
                'h14_cutoff_date' => '2026-11-06',
                'h7_cutoff_date' => '2026-11-13',
                'signature_hash' => hash('sha256', 'BLK-MICE-2026-001-SALT'),
            ]
        );

        // 5. Banquet Order
        HtlBanquetOrder::firstOrCreate(
            ['beo_code' => 'BEO-2026-089'],
            [
                'id' => (string) Str::uuid(),
                'mice_contract_id' => $contract1->id,
                'title' => 'Gala Dinner Plenary Summit 600 Pax',
                'meal_period' => 'DINNER',
                'guaranteed_pax' => 600,
                'prepared_pax' => 630,
                'safety_buffer_ratio' => 1.05,
                'event_date' => '2026-11-21',
                'event_duration_hours' => 4.0,
                'hourly_labor_rate_idr' => 50000,
                'total_food_cogs_idr' => 120000000,
                'total_bev_cogs_idr' => 45000000,
                'total_labor_cost_idr' => 9200000,
                'status' => 'CONFIRMED',
                'recipe_bom_json' => [
                    'menu' => 'Nusantara Royal 6-Course',
                    'buffer_pax' => 30,
                ],
                'signature_hash' => hash('sha256', 'BEO-2026-089-SALT'),
            ]
        );

        // 6. Milestone Schedules
        HtlWeddingMilestoneSchedule::firstOrCreate(
            ['mice_contract_id' => $contract1->id, 'milestone_index' => 1],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Deposit Konfirmasi SPK (DP 20%)',
                'percentage' => 20.0,
                'amount_idr' => 150000000,
                'due_date' => '2026-11-15',
                'status' => 'PAID',
                'paid_amount_idr' => 150000000,
                'paid_at' => now(),
                'payment_reference' => 'PAY-MICE-T1-8812',
            ]
        );

        HtlWeddingMilestoneSchedule::firstOrCreate(
            ['mice_contract_id' => $contract1->id, 'milestone_index' => 2],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Termin II (Mid-term Booking 30%)',
                'percentage' => 30.0,
                'amount_idr' => 225000000,
                'due_date' => '2026-11-16',
                'status' => 'PENDING',
                'paid_amount_idr' => 0,
            ]
        );

        // 7. Exhibition Booths
        HtlExhibitionBoothReservation::firstOrCreate(
            ['reservation_code' => 'BTH-2026-001'],
            [
                'id' => (string) Str::uuid(),
                'mice_contract_id' => $contract1->id,
                'booth_number' => 'Stan A-01',
                'booth_type' => 'SHELL_3X3',
                'grid_x' => 0,
                'grid_y' => 12,
                'width_m' => 3,
                'length_m' => 3,
                'electricity_kva' => 2.0,
                'is_dedicated_lan' => true,
                'base_rental_idr' => 15000000,
                'utility_charge_idr' => 3900000,
                'total_charge_idr' => 18900000,
                'status' => 'CONFIRMED',
                'estimated_daily_footfall' => 550,
            ]
        );
    }
}
