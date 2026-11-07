<?php

declare(strict_types=1);

namespace Modules\Hotel\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HtlAlliancePartner;
use Modules\Hotel\Domain\Models\HtlAwardNightPricingRule;
use Modules\Hotel\Domain\Models\HtlPersonalizedTravelCampaign;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;

class HotelFase112GlobalLoyaltySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Alliance Partners
        $partners = [
            [
                'partner_code' => 'GARUDA_AIR',
                'partner_name' => 'Garuda Indonesia Airlines',
                'category' => 'AIRLINE',
                'base_exchange_ratio' => 10.0000,
                'transfer_fee_percentage' => 0.0350,
                'min_points_transfer' => 1000,
                'max_points_transfer_daily' => 100000,
                'is_active' => true,
                'api_endpoint' => 'https://api.partner.garuda.example.com/v1/rewards',
            ],
            [
                'partner_code' => 'SINGAPORE_AIR',
                'partner_name' => 'Singapore Airlines KrisFlyer',
                'category' => 'AIRLINE',
                'base_exchange_ratio' => 12.0000,
                'transfer_fee_percentage' => 0.0400,
                'min_points_transfer' => 2000,
                'max_points_transfer_daily' => 150000,
                'is_active' => true,
                'api_endpoint' => 'https://api.krisflyer.example.com/v2/accrual',
            ],
            [
                'partner_code' => 'KAI_EXECUTIVE',
                'partner_name' => 'Kereta Api Indonesia Panoramic & Luxury',
                'category' => 'RAILWAY',
                'base_exchange_ratio' => 5.0000,
                'transfer_fee_percentage' => 0.0250,
                'min_points_transfer' => 500,
                'max_points_transfer_daily' => 50000,
                'is_active' => true,
                'api_endpoint' => 'https://api.kai.example.com/loyalty',
            ],
        ];

        foreach ($partners as $p) {
            HtlAlliancePartner::updateOrCreate(
                ['partner_code' => $p['partner_code']],
                array_merge($p, ['id' => (string) Str::uuid()])
            );
        }

        // 2. Award Night Pricing Rules
        $rules = [
            [
                'property_reference' => 'PROP-BALI-01',
                'room_type_reference' => 'DELUXE_OCEAN',
                'base_points_required' => 15000,
                'marginal_cost_idr' => 300000,
                'floor_multiplier' => 1.15,
                'seasonality_multiplier' => 1.20,
                'is_active' => true,
            ],
            [
                'property_reference' => 'PROP-LBN-02',
                'room_type_reference' => 'VILLA_CLIFF',
                'base_points_required' => 25000,
                'marginal_cost_idr' => 500000,
                'floor_multiplier' => 1.15,
                'seasonality_multiplier' => 1.35,
                'is_active' => true,
            ],
            [
                'property_reference' => 'PROP-JKT-03',
                'room_type_reference' => 'EXECUTIVE_SUITE',
                'base_points_required' => 18000,
                'marginal_cost_idr' => 350000,
                'floor_multiplier' => 1.15,
                'seasonality_multiplier' => 1.00,
                'is_active' => true,
            ],
        ];

        foreach ($rules as $r) {
            HtlAwardNightPricingRule::updateOrCreate(
                [
                    'property_reference' => $r['property_reference'],
                    'room_type_reference' => $r['room_type_reference'],
                ],
                array_merge($r, ['id' => (string) Str::uuid()])
            );
        }

        // 3. Demo Travel Passes & Batches
        $demoPasses = [
            [
                'pass_number' => 'TP-DEMO-0001',
                'user_reference' => 'USR-DEMO-SULTAN',
                'tier' => 'BLACK',
                'cumulative_nights' => 82,
                'cumulative_spend_idr' => 290000000,
                'pts_balance' => 85000,
                'is_active' => true,
            ],
            [
                'pass_number' => 'TP-DEMO-0002',
                'user_reference' => 'USR-DEMO-TRAVELER',
                'tier' => 'PLATINUM',
                'cumulative_nights' => 44,
                'cumulative_spend_idr' => 110000000,
                'pts_balance' => 32000,
                'is_active' => true,
            ],
            [
                'pass_number' => 'TP-DEMO-0003',
                'user_reference' => 'USR-DEMO-WEEKENDER',
                'tier' => 'GOLD',
                'cumulative_nights' => 22,
                'cumulative_spend_idr' => 38000000,
                'pts_balance' => 14000,
                'is_active' => true,
            ],
        ];

        foreach ($demoPasses as $dp) {
            $pass = HtlTravelPass::updateOrCreate(
                ['pass_number' => $dp['pass_number']],
                array_merge($dp, ['id' => (string) Str::uuid()])
            );

            // Add batches
            HtlTravelPassPointBatch::create([
                'id' => (string) Str::uuid(),
                'travel_pass_id' => $pass->id,
                'batch_number' => 'BTH-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
                'originating_line' => 'HTL_ROOM',
                'initial_pts' => $dp['pts_balance'],
                'remaining_pts' => $dp['pts_balance'],
                'liability_per_pt_idr' => 100,
                'total_liability_idr' => $dp['pts_balance'] * 100,
                'state' => 'ACTIVE',
                'reference_code' => 'SEED-INITIAL-BALANCE',
                'earned_at' => now(),
                'expires_at' => now()->addDays(730),
            ]);

            // Add campaign
            HtlPersonalizedTravelCampaign::create([
                'id' => (string) Str::uuid(),
                'travel_pass_id' => $pass->id,
                'campaign_code' => 'CAMP-BALI-SUMMER',
                'offer_title' => 'Eksplorasi Resor Bali & Konser Sunset Beach Club',
                'destination_property_reference' => 'PROP-BALI-01',
                'featured_package_title' => 'Bali Summer Sunset VIP Getaway',
                'points_required_discounted' => 25000,
                'valid_until' => now()->addDays(30),
                'affinity_score' => 0.9500,
                'interaction_status' => 'DELIVERED',
            ]);
        }
    }
}
