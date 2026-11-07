<?php

declare(strict_types=1);

namespace Modules\Venue\database\seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Venue\Domain\Models\BiometricCredential;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateTurnstile;
use Modules\Venue\Domain\Models\PersonalizedPromo;
use Modules\Venue\Domain\Models\ZoneWpsMetric;

final class VenueCrowdDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Pengguna Demo
        $operator = User::firstOrCreate(
            ['email' => 'crowd.safety@superweb.test'],
            ['name' => 'Bambang Soedirman (Safety Lead)', 'password' => bcrypt('secret123'), 'role' => 'crowd_safety']
        );

        $manager = User::firstOrCreate(
            ['email' => 'venue.mgr@superweb.test'],
            ['name' => 'Diana Putri (Venue GM)', 'password' => bcrypt('secret123'), 'role' => 'venue_manager']
        );

        $guests = [];
        for ($i = 1; $i <= 10; $i++) {
            $guests[$i] = User::firstOrCreate(
                ['email' => "guest.vip{$i}@superweb.test"],
                ['name' => "VIP Festival Guest #{$i}", 'password' => bcrypt('secret123'), 'role' => 'customer']
            );
        }

        // 2. Buat Venue dan Event Festival Neon
        $venue = EntertainmentVenue::firstOrCreate(
            ['venue_code' => 'VEN-BALI-ARENA'],
            [
                'name' => 'Bali International Sound Arena',
                'venue_type' => 'FESTIVAL_GROUNDS',
                'city' => 'Badung, Bali',
                'min_age_requirement' => 18,
                'max_legal_capacity' => 5000,
            ]
        );

        $event = EntertainmentEvent::firstOrCreate(
            ['event_code' => 'EVT-NEON-FEST-2026'],
            [
                'venue_id' => $venue->id,
                'title' => 'Neon Horizon EDM Festival 2026',
                'doors_open_at' => Carbon::now()->addHours(2),
                'ticket_floor_price_idr' => 750000,
                'ticket_ceiling_price_idr' => 3500000,
                'current_ticket_price_idr' => 1250000,
                'status' => 'on_sale',
            ]
        );

        // 3. Buat Zona Kerumunan Sektoral
        $zoneStage = EntertainmentZone::firstOrCreate(
            ['zone_code' => 'ZN-STAGE-01'],
            [
                'venue_id' => $venue->id,
                'name' => 'Main Stage Mosh Pit',
                'capacity_limit' => 2000,
            ]
        );

        $zoneVip = EntertainmentZone::firstOrCreate(
            ['zone_code' => 'ZN-VIP-02'],
            [
                'venue_id' => $venue->id,
                'name' => 'VIP Deck & Sky Lounge',
                'capacity_limit' => 600,
            ]
        );

        $zoneBar = EntertainmentZone::firstOrCreate(
            ['zone_code' => 'ZN-BAR-03'],
            [
                'venue_id' => $venue->id,
                'name' => 'Central F&B Pavilion',
                'capacity_limit' => 1200,
            ]
        );

        $zoneGates = EntertainmentZone::firstOrCreate(
            ['zone_code' => 'ZN-GATES-04'],
            [
                'venue_id' => $venue->id,
                'name' => 'Main Ingress Gates Hub',
                'capacity_limit' => 1200,
            ]
        );

        // 4. Buat Perangkat Turnstile Fisik (8 Gerbang IoT)
        for ($g = 1; $g <= 8; $g++) {
            GateTurnstile::firstOrCreate(
                ['gate_code' => sprintf('TURNSTILE-GATE-%02d', $g)],
                [
                    'venue_id' => $venue->id,
                    'zone_id' => $zoneGates->id,
                    'gate_name' => sprintf('Turnstile Entrance Gate %02d', $g),
                    'turnstile_type' => $g <= 2 ? 'FLAP_BARRIER' : 'BIOMETRIC_TRIPOD',
                    'is_emergency_exit' => ($g >= 7),
                    'is_locked' => false,
                    'total_scans_in' => 150 * $g,
                    'total_scans_out' => 20 * $g,
                    'last_scan_at' => Carbon::now(),
                ]
            );
        }

        // 5. Buat Kredensial Biometrik Anggota
        foreach ($guests as $idx => $guest) {
            $isRevoked = ($idx === 10);
            $templateHash = hash('sha256', sprintf('FACE-EMBEDDING-%d-SALT-2026', $guest->id));

            BiometricCredential::updateOrCreate(
                ['user_id' => $guest->id],
                [
                    'credential_code' => sprintf('BIO-CRED-%04d', $guest->id),
                    'biometric_template_hash' => $isRevoked ? hash('sha256', 'SCRUBBED-DEMO-'.$guest->id) : $templateHash,
                    'liveness_signature_hash' => hash('sha256', 'LIVE-SIG-'.$guest->id),
                    'liveness_score_bps' => $isRevoked ? 2000 : 9800,
                    'sensor_model' => 'FACECAM-3D-PRO',
                    'status' => $isRevoked ? 'REVOKED' : 'ENROLLED_ACTIVE',
                    'consented_at' => Carbon::now()->subDays(3),
                    'revoked_at' => $isRevoked ? Carbon::now()->subHours(1) : null,
                    'is_active' => ! $isRevoked,
                ]
            );
        }

        // 6. Buat Metrik Telemetri Antrean Layanan Bar (WPS)
        for ($s = 1; $s <= 4; $s++) {
            $waitMinutes = ($s === 3) ? 4 : 2;
            $wpsScore = max(40, 100 - ($waitMinutes * 15));

            ZoneWpsMetric::create([
                'zone_id' => $zoneBar->id,
                'event_id' => $event->id,
                'station_number' => $s,
                'active_servers_count' => 3,
                'arrival_rate_bps' => 2500,
                'service_rate_bps' => 2000,
                'utilization_bps' => 8000,
                'average_wait_minutes' => $waitMinutes,
                'wps_score' => $wpsScore,
                'reinforcement_needed' => ($s === 3),
                'recommended_additional_servers' => ($s === 3) ? 2 : 0,
                'recorded_at' => Carbon::now(),
            ]);
        }

        // 7. Buat Insiden Kerumunan Demo
        CrowdCrushIncident::firstOrCreate(
            ['incident_code' => 'CRUSH-DEMO-STAGE-01'],
            [
                'event_id' => $event->id,
                'zone_id' => $zoneStage->id,
                'risk_level' => 'HIGH',
                'headcount_at_incident' => 1050,
                'evacuation_corridor' => 'CORRIDOR-EAST',
                'reported_by_user_id' => $operator->id,
                'status' => 'DISPATCHED',
                'ambulance_dispatched' => false,
                'lockdown_initiated_at' => null,
            ]
        );

        // 8. Buat Promo On-Site VIP
        for ($p = 1; $p <= 5; $p++) {
            PersonalizedPromo::create([
                'user_id' => $guests[$p]->id,
                'zone_id' => $zoneVip->id,
                'promo_dispatch_code' => sprintf('VIP-NEON-%d-%04d', $event->id, $p * 111),
                'promo_title' => 'VIP Member 20% OFF Beverage Voucher',
                'discount_percent' => 20,
                'discount_bps' => 2000,
                'floor_price_idr' => 50000,
                'status' => $p <= 2 ? 'REDEEMED' : 'DISPATCHED',
                'delivered_at' => Carbon::now()->subMinutes(15),
                'redeemed_at' => $p <= 2 ? Carbon::now()->subMinutes(5) : null,
                'expires_at' => Carbon::now()->addHours(2),
            ]);
        }
    }
}
