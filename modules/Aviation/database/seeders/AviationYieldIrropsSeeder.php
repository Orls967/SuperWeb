<?php

declare(strict_types=1);

namespace Modules\Aviation\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Aviation\Domain\Algorithms\FareBucketYieldOptimizer;
use Modules\Aviation\Domain\Models\AviationAirline;
use Modules\Aviation\Domain\Models\AviationCareVoucher;
use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;
use Modules\Aviation\Domain\Models\AviationCodeshareFlight;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Domain\Models\AviationDisruptionPassenger;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;
use Modules\Aviation\Domain\Models\AviationLoyaltyTransaction;
use Modules\Aviation\Domain\Models\AviationParametricClaim;
use Modules\Aviation\Domain\Models\AviationScheduleVersion;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Domain\Models\AviationYieldBucket;
use Modules\Aviation\Domain\StateMachines\CodeshareAgreementStateMachine;
use Modules\Aviation\Domain\StateMachines\DisruptionCaseStateMachine;
use Modules\Aviation\Domain\StateMachines\ScheduleVersionStateMachine;

class AviationYieldIrropsSeeder extends Seeder
{
    public function run(): void
    {
        $airlines = AviationAirline::all();
        if ($airlines->isEmpty()) {
            return;
        }

        $airline1 = $airlines->first();
        $airline2 = $airlines->count() > 1 ? $airlines->skip(1)->first() : $airline1;

        // 1. Schedule Version Musiman S26
        $schedVersion = AviationScheduleVersion::firstOrCreate(
            [
                'airline_id' => $airline1->id,
                'season_code' => 'S26',
                'version_number' => 1,
            ],
            [
                'status' => ScheduleVersionStateMachine::STATUS_ACTIVE,
                'effective_from' => '2026-03-29',
                'effective_until' => '2026-10-24',
                'published_at' => Carbon::now()->subMonths(2),
                'notes' => 'IATA Northern Summer Season 2026 Jadwal Terpadu',
            ]
        );

        // 2. Perjanjian Codeshare
        if ($airline1->id !== $airline2->id) {
            $agreement = AviationCodeshareAgreement::firstOrCreate(
                ['agreement_code' => 'CS-GA-QG-2026'],
                [
                    'operating_airline_id' => $airline1->id,
                    'marketing_airline_id' => $airline2->id,
                    'agreement_type' => 'free_sale',
                    'revenue_split_bps' => 8500, // 85% operating, 15% marketing
                    'proration_rule' => 'straight_rate',
                    'status' => CodeshareAgreementStateMachine::STATUS_ACTIVE,
                    'valid_from' => '2026-01-01',
                    'valid_until' => '2026-12-31',
                ]
            );

            // Pemetaan penerbangan codeshare jika ada flight
            $flights = AviationFlight::where('airline_id', $airline1->id)->take(3)->get();
            foreach ($flights as $fIdx => $flight) {
                AviationCodeshareFlight::firstOrCreate(
                    [
                        'codeshare_agreement_id' => $agreement->id,
                        'operating_flight_id' => $flight->id,
                        'marketing_flight_number' => 'QG'.(5000 + $fIdx),
                    ],
                    [
                        'allotted_seats' => 20,
                        'status' => 'active',
                    ]
                );
            }
        }

        // 3. Akun Loyalitas Frequent Flyer (Berbagai Tier)
        $tiers = ['classic', 'silver', 'gold', 'platinum'];
        $loyaltyAccounts = [];
        for ($i = 1; $i <= 8; $i++) {
            $tier = $tiers[($i - 1) % 4];
            $tierMiles = match ($tier) {
                'platinum' => 75000,
                'gold' => 45000,
                'silver' => 20000,
                default => 2500,
            };

            $acc = AviationLoyaltyAccount::firstOrCreate(
                ['membership_number' => sprintf('FFP-%d-%06d', $airline1->id, 100000 + $i)],
                [
                    'airline_id' => $airline1->id,
                    'passenger_party_ref' => (string) $i,
                    'current_tier' => $tier,
                    'tier_miles' => $tierMiles,
                    'redeemable_miles' => $tierMiles + 5000,
                    'status' => 'active',
                    'tier_expiry_date' => Carbon::now()->addYear()->toDateString(),
                ]
            );
            $loyaltyAccounts[] = $acc;

            // Transaksi histori akrual
            AviationLoyaltyTransaction::firstOrCreate(
                ['transaction_reference' => 'TX-INIT-'.$acc->membership_number],
                [
                    'loyalty_account_id' => $acc->id,
                    'transaction_type' => 'flight_accrual',
                    'miles_delta' => $tierMiles,
                    'tier_miles_delta' => $tierMiles,
                    'running_balance' => $acc->redeemable_miles,
                    'nonce_claim_hash' => hash('sha256', 'INIT-BALANCE-'.$acc->membership_number),
                ]
            );
        }

        // 4. Yield Buckets untuk Penerbangan Aktif
        $optimizer = app(FareBucketYieldOptimizer::class);
        $sampleFlights = AviationFlight::take(5)->get();
        foreach ($sampleFlights as $flt) {
            $capacity = (int) $flt->capacity_seats;
            $baseFare = (int) $flt->base_price_minor_unit;
            $classes = [
                ['code' => 'Y', 'fare' => (int) round($baseFare * 1.5), 'mean_demand' => (int) round($capacity * 0.20), 'std_dev' => 6],
                ['code' => 'B', 'fare' => (int) round($baseFare * 1.25), 'mean_demand' => (int) round($capacity * 0.25), 'std_dev' => 8],
                ['code' => 'M', 'fare' => (int) round($baseFare * 1.0), 'mean_demand' => (int) round($capacity * 0.30), 'std_dev' => 10],
                ['code' => 'Q', 'fare' => (int) round($baseFare * 0.75), 'mean_demand' => (int) round($capacity * 0.35), 'std_dev' => 12],
            ];

            $res = $optimizer->calculateProtectionAndBookingLimits($capacity, $classes);
            foreach ($res['buckets'] as $b) {
                AviationYieldBucket::firstOrCreate(
                    [
                        'flight_id' => $flt->id,
                        'bucket_code' => $b['code'],
                    ],
                    [
                        'cabin_class' => 'economy',
                        'base_price_minor_unit' => $b['fare'],
                        'allocated_capacity' => $b['booking_limit'],
                        'sold_count' => 5,
                        'protection_level' => $b['protection_level'],
                        'booking_limit' => $b['booking_limit'],
                        'is_closed' => false,
                        'algorithm_certificate_hash' => $res['certificate_hash'],
                    ]
                );
            }
        }

        // 5. Kasus Disrupsi Utama: Erupsi Gunung Agung (DPS VONA RED)
        $dpsFlight = AviationFlight::where('flight_number', 'GA-402')->first() ?? $sampleFlights->first();
        if ($dpsFlight !== null) {
            $disruptionCase = AviationDisruptionCase::firstOrCreate(
                ['case_number' => 'IRR-20261108-DPS-ERUPTION'],
                [
                    'flight_id' => $dpsFlight->id,
                    'incident_category' => 'FORCE_MAJEURE_VOLCANIC_ERUPTION',
                    'delay_minutes' => 1080, // 18 jam
                    'status' => DisruptionCaseStateMachine::STATUS_REACCOMMODATING,
                    'regulatory_category' => 'CAT6_CANCELLED',
                    'duty_of_care_required' => true,
                    'cash_compensation_required' => false, // Force majeure
                    'hotel_required' => true,
                    'incident_description' => 'Erupsi eksplosif Gunung Agung menyemburkan abu vulkanik hingga FL320. NOTAM penutupan Bandara DPS diterbitkan.',
                    'detected_at' => Carbon::now()->subHours(2),
                ]
            );

            // Buat manifes 10 penumpang terdampak
            $tickets = AviationTicket::where('flight_id', $dpsFlight->id)->take(10)->get();
            foreach ($tickets as $tIdx => $tkt) {
                $pax = AviationDisruptionPassenger::firstOrCreate(
                    [
                        'disruption_case_id' => $disruptionCase->id,
                        'ticket_id' => $tkt->id,
                    ],
                    [
                        'passenger_name' => $tkt->passenger_name,
                        'priority_score' => 15000 - ($tIdx * 1000),
                        'loyalty_tier' => $tIdx < 2 ? 'platinum' : ($tIdx < 5 ? 'gold' : 'classic'),
                        'special_service_request' => $tIdx === 0 ? 'UMNR' : null,
                        'reaccommodation_status' => 'pending',
                        'hotel_reservation_ref' => 'HTL-RES-DPS-9988',
                    ]
                );

                // Buat e-voucher meal & refreshment
                AviationCareVoucher::firstOrCreate(
                    ['voucher_code' => 'VCR-MEAL-DPS-'.($tIdx + 1)],
                    [
                        'disruption_passenger_id' => $pax->id,
                        'voucher_type' => 'meal',
                        'nominal_amount_minor_unit' => 15000000, // Rp150.000
                        'currency' => 'IDR',
                        'is_redeemed' => false,
                        'expires_at' => Carbon::now()->addHours(24),
                    ]
                );

                // Rekam klaim asuransi parametrik
                AviationParametricClaim::firstOrCreate(
                    [
                        'disruption_case_id' => $disruptionCase->id,
                        'policy_number' => 'POL-DPS-ERUPT-'.($tIdx + 1),
                    ],
                    [
                        'passenger_party_ref' => (string) $pax->id,
                        'trigger_type' => 'VOLCANIC_ERUPTION_CLOSURE',
                        'payout_amount_minor_unit' => 100000000, // Rp1.000.000
                        'claim_status' => 'approved',
                        'external_reference' => 'PTR-DPS-VONA-'.($tIdx + 1),
                    ]
                );
            }
        }
    }
}
