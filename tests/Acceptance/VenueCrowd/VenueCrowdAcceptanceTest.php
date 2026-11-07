<?php

declare(strict_types=1);

namespace Tests\Acceptance\VenueCrowd;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Venue\Application\Actions\ExecuteCrowdCrushEmergencyAction;
use Modules\Venue\Application\Services\VenueCrowdAndBiometricService;
use Modules\Venue\Domain\Algorithms\Biometric\BiometricLivenessMatcherEngine;
use Modules\Venue\Domain\Algorithms\CrowdSafety\CrowdDensitySurgePredictor;
use Modules\Venue\Domain\Algorithms\Experience\WaitPerServiceGovernor;
use Modules\Venue\Domain\Algorithms\Personalization\PersonalizedOfferContextRouter;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Tests\TestCase;

/**
 * Acceptance Test Terpadu (L.2) — Fase 117: Guest Experience AI, Biometric Entry & Crowd Safety.
 * Menguji 5 alur kritis operasional venue: Biometric Face-ID, Crowd Surge Prediction,
 * Emergency Evacuation Saga, Wait Per Service Scoring, dan Personalized VIP Offer Routing.
 */
class VenueCrowdAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected EntertainmentVenue $venue;

    protected EntertainmentZone $zone;

    protected EntertainmentEvent $event;

    protected User $operatorUser;

    protected VenueCrowdAndBiometricService $crowdService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crowdService = app(VenueCrowdAndBiometricService::class);

        $this->operatorUser = User::create([
            'name' => 'Crowd Safety Officer',
            'email' => 'crowd.officer@colosseum.id',
            'password' => bcrypt('password'),
            'role' => 'crowd_safety_officer',
        ]);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-AT-CROWD-01',
            'name' => 'Colosseum Festival Park Arena',
            'venue_type' => 'FESTIVAL_GROUNDS',
            'city' => 'Denpasar',
            'max_legal_capacity' => 10000,
            'min_age_requirement' => 18,
        ]);

        $this->zone = EntertainmentZone::create([
            'venue_id' => $this->venue->id,
            'zone_code' => 'ZN-EDM-ARENA',
            'name' => 'Ultra Main Stage Arena',
            'capacity_limit' => 2000,
            'current_occupancy' => 1800,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-CROWD-SUMMER-2026',
            'venue_id' => $this->venue->id,
            'title' => 'Summer Neon Electronic Festival 2026',
            'doors_open_at' => now()->addDays(2),
            'ticket_floor_price_idr' => 750_000,
            'ticket_ceiling_price_idr' => 3_500_000,
            'current_ticket_price_idr' => 1_500_000,
        ]);
    }

    /**
     * AC-117.1: Biometric Face-ID door entry, liveness verification, and one-way template hashing.
     */
    public function test_biometric_face_id_enrolment_liveness_and_one_way_hash_entry(): void
    {
        // 1. Pendaftaran kredensial biometrik dengan pembuktian liveness
        $credential = $this->crowdService->registerBiometricCredential(
            userId: 501,
            rawBiometricFeatureVector: 'VECTOR-FACE-NODES-501-ULTRA-SECURE',
            livenessProof: 'CHALLENGE-BLINK-SUCCESS'
        );

        $this->assertNotNull($credential->id);
        $this->assertStringStartsWith('BIO-', $credential->credential_code);
        $this->assertEquals(64, strlen($credential->biometric_template_hash));

        // 2. Verifikasi akses masuk turnstile gerbang berhasil
        $match = $this->crowdService->verifyBiometricDoorEntry(
            $credential,
            'VECTOR-FACE-NODES-501-ULTRA-SECURE',
            'CHALLENGE-BLINK-SUCCESS'
        );
        $this->assertTrue($match);

        // 3. Algoritma V13 BiometricLivenessMatcherEngine terverifikasi
        $engine = app(BiometricLivenessMatcherEngine::class);
        $certificate = $engine->match(
            userId: 501,
            featureVector: 'VECTOR-FACE-NODES-501-ULTRA-SECURE',
            challengeProof: 'CHALLENGE-BLINK-SUCCESS',
            saltSecret: 'SECRET_VENUE_SALT_2026'
        );

        $this->assertTrue($certificate->isMatched);
        $this->assertGreaterThanOrEqual(8500, $certificate->livenessScoreBps);
        $this->assertTrue($certificate->verifySignature('SECRET_VENUE_SALT_2026'));
    }

    /**
     * AC-117.2 & AC-117.3: Crowd density surge prediction and automatic gate restriction.
     */
    public function test_crowd_density_surge_prediction_and_automatic_gate_restriction(): void
    {
        // Saat ini 1.850 di zona berkapasitas 2.000 dengan laju masuk tinggi (inflow 20, outflow 2)
        // Prediksi 15m: 1.850 + 15 * (18) = 2.120 (melebihi limit 2.000)
        $telemetry = $this->crowdService->analyzeZoneCrowdSafety($this->zone, 1850);

        $this->assertEquals(1850, $telemetry->current_headcount);
        $this->assertGreaterThanOrEqual(2000, $telemetry->predicted_headcount_15m);
        $this->assertEquals('RESTRICT_ACCESS', $telemetry->recommended_action);
        $this->assertTrue($telemetry->gate_restricted);

        // Verifikasi Algoritma V14 CrowdDensitySurgePredictor
        $predictor = app(CrowdDensitySurgePredictor::class);
        $result = $predictor->predictSurge(
            zoneId: $this->zone->id,
            currentHeadcount: 1850,
            zoneAreaSqm: 500,
            legalCapacityLimit: 2000,
            inflowVelocityPerMinute: 20,
            outflowVelocityPerMinute: 2
        );

        $this->assertTrue($result->gateRestricted);
        $this->assertEquals('RESTRICT_ACCESS', $result->recommendedAction);
        $this->assertGreaterThan(40000, $result->predictedDensityBps); // > 4.0 orang/m^2
    }

    /**
     * AC-117.3: Emergency Evacuation Saga and compensating gate release.
     */
    public function test_emergency_evacuation_saga_and_compensating_gate_release(): void
    {
        $action = app(ExecuteCrowdCrushEmergencyAction::class);
        $incident = $action->execute(
            eventId: $this->event->id,
            zoneId: $this->zone->id,
            operatorUserId: $this->operatorUser->id,
            operatorNotes: 'Simulasi ancaman desakan massa di pintu panggung utama'
        );

        $this->assertEquals('CRITICAL', $incident->severity_level);
        $this->assertStringStartsWith('INC-', $incident->incident_code);
        $this->assertEquals('EVACUATION_STANDBY', $incident->status);

        // Verifikasi resolusi dan pemulihan sistem
        $resolvedIncident = $action->resolveIncident(
            incident: $incident,
            operatorUserId: $this->operatorUser->id,
            resolutionNotes: 'Arus kerumunan teralihkan ke Gate B, situasi aman terkendali'
        );

        $this->assertEquals('RESOLVED_CLEAR', $resolvedIncident->status);
        $this->assertNotNull($resolvedIncident->resolved_at);
    }

    /**
     * AC-117.4: Wait Per Service (WPS) scoring and bartender shift reinforcement signal.
     */
    public function test_wait_per_service_scoring_and_bartender_reinforcement_signal(): void
    {
        $governor = app(WaitPerServiceGovernor::class);

        // Kasus antrean jenuh: 3 kasir, laju kedatangan 8 pesanan/menit, kapasitas kasir 2 pesanan/menit
        // Utilisasi = 8 / (3 * 2) = 1.33 (> 1.0) -> Antrean membludak
        $resultSaturated = $governor->calculateWps(
            stationId: 101,
            activeServersCount: 3,
            arrivalRatePerMinuteBps: 8000,
            serviceRatePerMinuteBps: 2000,
            maxTolerableWaitMinutes: 15
        );

        $this->assertLessThan(60, $resultSaturated->wpsScore);
        $this->assertTrue($resultSaturated->reinforcementNeeded);
        $this->assertGreaterThan(0, $resultSaturated->recommendedAdditionalServers);

        // Kasus antrean lancar: 6 kasir, laju kedatangan 6 pesanan/menit, kapasitas kasir 2 pesanan/menit
        // Utilisasi = 6 / (6 * 2) = 0.50 -> Sangat optimal
        $resultOptimal = $governor->calculateWps(
            stationId: 101,
            activeServersCount: 6,
            arrivalRatePerMinuteBps: 6000,
            serviceRatePerMinuteBps: 2000,
            maxTolerableWaitMinutes: 15
        );

        $this->assertGreaterThanOrEqual(80, $resultOptimal->wpsScore);
        $this->assertFalse($resultOptimal->reinforcementNeeded);
        $this->assertEquals(0, $resultOptimal->recommendedAdditionalServers);
    }

    /**
     * AC-117.5: Personalized VIP offer context routing and anti-fatigue margin guardrail.
     */
    public function test_personalized_vip_offer_context_routing_and_anti_fatigue_guardrail(): void
    {
        $router = app(PersonalizedOfferContextRouter::class);
        $now = time();

        // 1. VIP Platinum: Diskon 25%, Base Rp 2.000.000, COGS Floor Rp 1.600.000
        // Raw Diskon: 25% * 2.000.000 = Rp 500.000 -> Harga Rp 1.500.000 < COGS Floor Rp 1.600.000
        // Wajib dipotong ke batas lantai COGS Rp 1.600.000 (Floor Guardrail Triggered)
        $offerResult = $router->evaluateOffer(
            userId: 777,
            currentZoneId: $this->zone->id,
            membershipTierCode: 'VIP_PLATINUM',
            baseItemPriceIdr: 2_000_000,
            itemCogsFloorPriceIdr: 1_600_000,
            lastOfferTimestamp: $now - 4000, // Cooldown > 1 jam (lolos)
            offersSentTonightCount: 1,       // < 3 promo (lolos)
            currentTimestamp: $now
        );

        $this->assertTrue($offerResult->isEligible);
        $this->assertEquals(1_600_000, $offerResult->discountedPriceIdr);
        $this->assertTrue($offerResult->floorGuardrailTriggered);
        $this->assertStringStartsWith('PROMO-CTX-', $offerResult->offerDispatchCode);

        // 2. Anti-Fatigue Rejection: Jeda baru 15 menit (< 3.600s) -> Ditolak
        $blockedOffer = $router->evaluateOffer(
            userId: 777,
            currentZoneId: $this->zone->id,
            membershipTierCode: 'VIP_PLATINUM',
            baseItemPriceIdr: 2_000_000,
            itemCogsFloorPriceIdr: 1_600_000,
            lastOfferTimestamp: $now - 900, // Hanya 15 menit
            offersSentTonightCount: 2,
            currentTimestamp: $now
        );

        $this->assertFalse($blockedOffer->isEligible);
        $this->assertEquals('COOLDOWN_ACTIVE', $blockedOffer->rejectionReason);
    }
}
