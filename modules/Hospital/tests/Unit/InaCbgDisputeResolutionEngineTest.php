<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\InaCbgDisputeResolutionEngine;
use PHPUnit\Framework\TestCase;

class InaCbgDisputeResolutionEngineTest extends TestCase
{
    private InaCbgDisputeResolutionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new InaCbgDisputeResolutionEngine();
    }

    public function test_construct_dispute_appeal_for_primary_diagnosis_with_strong_evidence(): void
    {
        $result = $this->engine->constructDisputeAppeal(
            InaCbgDisputeResolutionEngine::DISPUTE_CODE_CODING_DIAGNOSIS,
            'I21.0', // STEMI
            ['RESUME_MEDIS', 'LAB_RESULT', 'RADIOLOGY_REPORT'],
            1500000000, // Rp 15.000.000
            800000000   // Rp 8.000.000 (disputed)
        );

        $this->assertSame(700000000, $result['contested_amount_cents']);
        $this->assertGreaterThanOrEqual(80, $result['appeal_viability_score']);
        $this->assertSame('SUBMIT_FORMAL_DISPUTE_TKMKB', $result['recommended_action']);
        $this->assertStringContainsString('I21.0', $result['defense_argument_template']);
    }

    public function test_low_evidence_recommends_write_off(): void
    {
        $result = $this->engine->constructDisputeAppeal(
            InaCbgDisputeResolutionEngine::DISPUTE_CODE_PROCEDURE_BUNDLING,
            'K35.8',
            [], // Tanpa bukti klinis pendukung
            500000000,
            400000000
        );

        $this->assertLessThan(60, $result['appeal_viability_score']);
        $this->assertSame('ACCEPT_ADJUDICATION_WRITE_OFF_DIFFERENCE', $result['recommended_action']);
    }
}
