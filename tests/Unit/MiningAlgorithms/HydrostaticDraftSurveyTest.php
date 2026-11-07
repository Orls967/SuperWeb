<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\Marine\HydrostaticDraftSurveyReconciler;
use Modules\Shared\Domain\Algorithms\Marine\HydrostaticDraftSurveyVerifier;
use Modules\Shared\Domain\Algorithms\Marine\HydrostaticTableEntry;
use Modules\Shared\Domain\Algorithms\Marine\SixPointDraftReading;
use Tests\TestCase;

class HydrostaticDraftSurveyTest extends TestCase
{
    public function test_hydrostatic_draft_survey_reconciles_tonnage(): void
    {
        $reconciler = new HydrostaticDraftSurveyReconciler;

        $table = [
            new HydrostaticTableEntry(draftM: 1.0, displacementTon: 1200.0, tpcTonPerCm: 15.0, lcfM: 0.0),
            new HydrostaticTableEntry(draftM: 2.0, displacementTon: 2750.0, tpcTonPerCm: 15.5, lcfM: 0.0),
            new HydrostaticTableEntry(draftM: 3.0, displacementTon: 4350.0, tpcTonPerCm: 16.0, lcfM: 0.0),
            new HydrostaticTableEntry(draftM: 4.0, displacementTon: 6000.0, tpcTonPerCm: 16.5, lcfM: 0.0),
            new HydrostaticTableEntry(draftM: 5.0, displacementTon: 7700.0, tpcTonPerCm: 17.0, lcfM: 0.0),
        ];

        $initial = new SixPointDraftReading(
            draftFpM: 0.95,
            draftFsM: 0.97,
            draftMpM: 1.00,
            draftMsM: 1.02,
            draftApM: 1.05,
            draftAsM: 1.07,
            waterDensityTPerM3: 1.025,
            ballastAndConsumablesTon: 0.0
        );

        $final = new SixPointDraftReading(
            draftFpM: 4.55,
            draftFsM: 4.57,
            draftMpM: 4.60,
            draftMsM: 4.62,
            draftApM: 4.65,
            draftAsM: 4.67,
            waterDensityTPerM3: 1.025,
            ballastAndConsumablesTon: 0.0
        );

        $result = $reconciler->reconcile(
            initialReading: $initial,
            finalReading: $final,
            hydrostaticTable: $table,
            weighbridgeTonnageTon: 5800.0,
            lengthBetweenPerpendicularsM: 90.0,
            statutoryTolerancePercent: 2.5
        );

        $this->assertGreaterThan(5000.0, $result->calculatedNetCargoTon);
        $this->assertLessThan(6500.0, $result->calculatedNetCargoTon);
        $this->assertNotEmpty($result->hmacSignature);
    }

    public function test_hydrostatic_verifier_certifies_vector(): void
    {
        $reconciler = new HydrostaticDraftSurveyReconciler;
        $table = [
            new HydrostaticTableEntry(draftM: 1.0, displacementTon: 1200.0),
            new HydrostaticTableEntry(draftM: 5.0, displacementTon: 7700.0),
        ];
        $initial = new SixPointDraftReading(0.95, 0.97, 1.00, 1.02, 1.05, 1.07);
        $final = new SixPointDraftReading(4.55, 4.57, 4.60, 4.62, 4.65, 4.67);
        $result = $reconciler->reconcile($initial, $final, $table, 5800.0);

        $isCertified = HydrostaticDraftSurveyVerifier::verify($result);
        $this->assertTrue($isCertified, 'Verifier resmi M9 harus menyatakan tersertifikasi.');
    }
}
