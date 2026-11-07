<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\Blasting\BlastGeometry;
use Modules\Shared\Domain\Algorithms\Blasting\ExplosiveProperty;
use Modules\Shared\Domain\Algorithms\Blasting\KcoBlastingFragmentationModel;
use Modules\Shared\Domain\Algorithms\Blasting\KcoBlastingFragmentationVerifier;
use Tests\TestCase;

class KcoBlastingFragmentationTest extends TestCase
{
    public function test_kco_model_evaluates_fragmentation_distribution(): void
    {
        $model = new KcoBlastingFragmentationModel;

        $geometry = new BlastGeometry(
            burdenM: 3.5,
            spacingM: 4.2,
            holeDiameterMm: 165.0,
            benchHeightM: 12.0,
            holeDepthM: 13.0,
            stemmingLengthM: 3.5,
            drillingAccuracyDeviationM: 0.1
        );

        $explosive = new ExplosiveProperty(
            explosiveType: 'ANFO',
            densityGPerCm3: 0.85,
            relativeWeightStrengthRws: 100.0,
            velocityOfDetonationMPerS: 4200.0
        );

        $result = $model->evaluate(
            geometry: $geometry,
            explosive: $explosive,
            rockMassFactorA: 8.0,
            chargeKgPerHole: 140.0,
            oversizeThresholdMm: 1000.0,
            finesThresholdMm: 25.0
        );

        $this->assertGreaterThan(50.0, $result->meanFragmentSizeX50Mm);
        $this->assertLessThan(600.0, $result->meanFragmentSizeX50Mm);
        $this->assertGreaterThan(0.5, $result->uniformityIndexN);
        $this->assertGreaterThanOrEqual(0.0, $result->oversizePercent);
        $this->assertLessThanOrEqual(100.0, $result->oversizePercent);
        $this->assertNotEmpty($result->hmacSignature);
    }

    public function test_kco_verifier_certifies_vector(): void
    {
        $model = new KcoBlastingFragmentationModel;
        $geometry = new BlastGeometry(
            burdenM: 3.5,
            spacingM: 4.2,
            holeDiameterMm: 165.0,
            benchHeightM: 12.0,
            holeDepthM: 13.0,
            stemmingLengthM: 3.5
        );
        $explosive = new ExplosiveProperty(explosiveType: 'ANFO');
        $result = $model->evaluate($geometry, $explosive);

        $isCertified = KcoBlastingFragmentationVerifier::verify($result);
        $this->assertTrue($isCertified, 'Verifier resmi M8 harus menyatakan tersertifikasi.');
    }
}
