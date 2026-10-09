<?php

namespace Modules\Venue\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Venue\Application\Services\VenueCrowdAndBiometricService;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use RuntimeException;
use Tests\TestCase;

class VenueCrowdAndBiometricTest extends TestCase
{
    use RefreshDatabase;

    protected VenueCrowdAndBiometricService $service;

    protected EntertainmentZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VenueCrowdAndBiometricService::class);

        $venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-BEACH-CROWD',
            'name' => 'Omnia Dayclub & Cliff VIP',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Uluwatu',
            'max_legal_capacity' => 1000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'venue_id' => $venue->id,
            'zone_code' => 'ZN-MAIN-STAGE',
            'name' => 'Main Stage Dancefloor',
            'capacity_limit' => 500,
            'current_occupancy' => 450,
        ]);
    }

    public function test_117_1_and_117_6_a_face_id_registration_and_tamper_evident_matching(): void
    {
        $credential = $this->service->registerBiometricCredential(
            userId: 777,
            rawBiometricFeatureVector: 'VECTOR-FACE-NODES-XYZ-12345',
            livenessProof: 'BLINK-SMILE-CHALLENGE-SUCCESS'
        );

        $this->assertEquals('BIO-', substr($credential->credential_code, 0, 4));
        $this->assertNotEmpty($credential->biometric_template_hash);

        // 1. Valid matching
        $match = $this->service->verifyBiometricDoorEntry(
            $credential,
            'VECTOR-FACE-NODES-XYZ-12345',
            'LIVE-OK'
        );
        $this->assertTrue($match);

        // 2. Mismatch attempt
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Face-ID template mismatch');

        $this->service->verifyBiometricDoorEntry(
            $credential,
            'VECTOR-FACE-NODES-WRONG-99999',
            'LIVE-OK'
        );
    }

    public function test_117_2_and_117_3_crowd_safety_prediction_and_gate_restriction(): void
    {
        // 470 in a 500 limit zone -> +10% predicted = 517 (>= 500) -> RESTRICT_ACCESS
        $telemetry = $this->service->analyzeZoneCrowdSafety($this->zone, 470);

        $this->assertEquals(470, $telemetry->current_headcount);
        $this->assertEquals(517, $telemetry->predicted_headcount_15m);
        $this->assertEquals('RESTRICT_ACCESS', $telemetry->recommended_action);
        $this->assertTrue($telemetry->gate_restricted);
    }

    public function test_117_5_and_117_6_d_personalized_promo_dispatch_deduplication(): void
    {
        $promo1 = $this->service->sendPersonalizedPromo(
            userId: 888,
            zone: $this->zone,
            promoTitle: 'VIP Champagne Bucket 20% Off',
            discountPercent: 20
        );

        // Dispatch second time with same parameters -> returns existing promo without duplicating!
        $promo2 = $this->service->sendPersonalizedPromo(
            userId: 888,
            zone: $this->zone,
            promoTitle: 'VIP Champagne Bucket 20% Off',
            discountPercent: 20
        );

        $this->assertEquals($promo1->id, $promo2->id);
        $this->assertEquals($promo1->promo_dispatch_code, $promo2->promo_dispatch_code);
    }
}
