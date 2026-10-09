<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\HospitalityOperationsPlaybookService;
use Tests\TestCase;

class HospitalityOperationsPlaybookTest extends TestCase
{
    use RefreshDatabase;

    protected HospitalityOperationsPlaybookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HospitalityOperationsPlaybookService::class);
    }

    public function test_sop_bible_immutable_version_and_worker_training_attestation(): void
    {
        // 1. Register SOP (280.1 & 280.4)
        $sop = $this->service->registerSop(
            sopCode: 'SOP-HOTEL-CHECKIN-V1',
            venueType: 'HOTEL',
            title: 'Front Desk VIP Guest Check-In Sequence',
            version: 'V1.0',
            lang: 'ID'
        );
        $this->assertTrue((bool) $sop->is_active_version_immutable);

        // 2. Unattested worker cannot be assigned to shift (280.4)
        try {
            $this->service->assignShift(
                shiftCode: 'SHIFT-MORNING-01',
                outletCode: 'HOTEL-BALI-RESORT',
                shiftType: 'MORNING',
                workerId: 'STAFF_NEW_ROOKIE',
                requiredSopCode: 'SOP-HOTEL-CHECKIN-V1'
            );
            $this->fail('Expected exception for unattested worker');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('has not completed mandatory training attestation', $e->getMessage());
        }

        // 3. Attest worker -> shift assignment succeeds (280.1 & 280.4)
        $this->service->attestWorkerTraining('STAFF_NEW_ROOKIE', 'SOP-HOTEL-CHECKIN-V1');
        $shift = $this->service->assignShift('SHIFT-MORNING-01', 'HOTEL-BALI-RESORT', 'MORNING', 'STAFF_NEW_ROOKIE', 'SOP-HOTEL-CHECKIN-V1');
        $this->assertTrue((bool) $shift->worker_authorized);
    }

    public function test_mystery_guest_audit_formulaic_grade_and_action_plan_trigger(): void
    {
        // 1. High score audit -> Grade A (280.3 & 280.4)
        // 95*0.35 + 90*0.35 + 92*0.30 = 33.25 + 31.50 + 27.60 = 92.35 => Grade A
        $goodAudit = $this->service->recordMysteryAudit(
            auditCode: 'AUD-RESTO-JAKARTA-01',
            outletCode: 'RESTO-SENOPATI',
            serviceScore: 95.0,
            cleanlinessScore: 90.0,
            foodQualityScore: 92.0
        );
        $this->assertEquals(92.35, (float) $goodAudit->composite_score);
        $this->assertEquals('A', $goodAudit->outlet_grade);
        $this->assertFalse((bool) $goodAudit->action_plan_required);
        $this->assertFalse((bool) $goodAudit->brand_scorecard_impacted);

        // 2. Poor score audit -> Grade D (< 70) triggers action plan & brand scorecard impact (280.5 Edge Case)
        // 60*0.35 + 65*0.35 + 50*0.30 = 21.0 + 22.75 + 15.0 = 58.75 => Grade D
        $badAudit = $this->service->recordMysteryAudit(
            auditCode: 'AUD-RESTO-SURABAYA-02',
            outletCode: 'RESTO-TUNJUNGAN',
            serviceScore: 60.0,
            cleanlinessScore: 65.0,
            foodQualityScore: 50.0
        );
        $this->assertEquals(58.75, (float) $badAudit->composite_score);
        $this->assertEquals('D', $badAudit->outlet_grade);
        $this->assertTrue((bool) $badAudit->action_plan_required);
        $this->assertTrue((bool) $badAudit->brand_scorecard_impacted);
    }

    public function test_hospitality_operations_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerSop('SOP-AUD', 'VENUE', 'SOP');
        $this->service->attestWorkerTraining('W1', 'SOP-AUD');
        $this->service->assignShift('SHIFT-AUD', 'OUT-1', 'NIGHT', 'W1', 'SOP-AUD');
        $this->service->recordMysteryAudit('AUD-1', 'OUT-1', 90.0, 90.0, 90.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: grade D audit without brand scorecard impact
        DB::table('hospitality_mystery_guest_audits')->insert([
            'audit_code' => 'AUD-DISCREPANT-D',
            'outlet_code' => 'OUT-1',
            'service_score' => 40.0,
            'cleanliness_score' => 40.0,
            'food_quality_score' => 40.0,
            'composite_score' => 40.0,
            'outlet_grade' => 'D',
            'action_plan_required' => false,
            'brand_scorecard_impacted' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
