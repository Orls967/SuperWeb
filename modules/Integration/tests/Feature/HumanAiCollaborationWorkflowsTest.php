<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\HumanAiCollaborationWorkflowsService;
use Tests\TestCase;

class HumanAiCollaborationWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    protected HumanAiCollaborationWorkflowsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HumanAiCollaborationWorkflowsService::class);
    }

    public function test_copilot_suggestion_never_auto_executes_and_records_human_decision(): void
    {
        // 1. Copilot suggestion created (270.1 & 270.4)
        $sug = $this->service->createSuggestion(
            roleCode: 'DOCTOR',
            modelVersion: 'CLINICAL_DIAG_LLM_V3',
            aiRecommendation: 'Prescribe Metformin 500mg daily'
        );

        $this->assertFalse((bool) $sug->is_auto_executed);
        $this->assertEquals('PENDING', $sug->human_decision);

        // 2. Doctor records human decision with user id accountability (270.7)
        $decided = $this->service->recordHumanDecision(
            suggestionCode: $sug->suggestion_code,
            reviewerUserId: 'DR_ANTON_SPPD',
            decision: 'MODIFIED',
            notes: 'Adjusted dosage to 250mg due to mild renal impairment'
        );

        $this->assertEquals('MODIFIED', $decided->human_decision);
        $this->assertEquals('DR_ANTON_SPPD', $decided->reviewer_user_id);
        $this->assertFalse((bool) $decided->is_auto_executed);
    }

    public function test_honeypot_sample_detects_careless_rubber_stamping(): void
    {
        // Deliberate honeypot error sample (270.6)
        $honeypot = $this->service->createSuggestion(
            roleCode: 'DISPATCHER',
            modelVersion: 'ROUTE_AI_V1',
            aiRecommendation: 'Dispatch 40-ton truck through pedestrian walkway bridge',
            isHoneypot: true
        );

        // Reviewer blindly accepts honeypot -> flagged as failed diligence
        $sloppyDecision = $this->service->recordHumanDecision(
            suggestionCode: $honeypot->suggestion_code,
            reviewerUserId: 'DISPATCHER_BOB',
            decision: 'ACCEPTED'
        );

        $this->assertFalse((bool) $sloppyDecision->honeypot_passed);
    }

    public function test_queue_metrics_and_hundred_percent_rejection_triggers_model_review(): void
    {
        // 1. Normal queue with 20% disagreement (270.2 & 270.3)
        $normalQueue = $this->service->updateQueueMetrics('QUEUE-MECHANIC-01', 'MECHANIC', 10, 0, 20.0);
        $this->assertFalse((bool) $normalQueue->model_review_required);

        // 2. 100% human disagreement rate triggers mandatory model review rather than forced adoption (270.5 Edge Case)
        $problemQueue = $this->service->updateQueueMetrics('QUEUE-AUDITOR-02', 'AUDITOR', 5, 1, 100.0);
        $this->assertTrue((bool) $problemQueue->model_review_required);
    }

    public function test_skill_augmentation_curriculum_creation(): void
    {
        // Skill augmentation loop from human feedback (270.2)
        $curriculum = $this->service->createAugmentationCurriculum(
            curriculumCode: 'CURR-DISP-OVERWEIGHT',
            targetRole: 'DISPATCHER',
            disagreementPattern: 'Human rejected route due to unmapped road height restriction',
            moduleNotes: 'Update route vector graph with overhead obstacle height metadata'
        );

        $this->assertNotNull($curriculum);
        $this->assertEquals('CURR-DISP-OVERWEIGHT', $curriculum->curriculum_code);
    }

    public function test_hitl_collaboration_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $s = $this->service->createSuggestion('CASHIER', 'V1', 'Upsell');
        $this->service->recordHumanDecision($s->suggestion_code, 'CASHIER_1', 'ACCEPTED');
        $this->service->updateQueueMetrics('Q-AUD', 'CASHIER', 1, 0, 10.0);
        $this->service->createAugmentationCurriculum('CURR-1', 'CASHIER', 'Pattern', 'Notes');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: suggestion auto-executed in HITL role
        DB::table('hitl_copilot_suggestions')->insert([
            'suggestion_code' => 'SUG-AUTO-ILLEGAL',
            'role_code' => 'DOCTOR',
            'model_version' => 'V1',
            'ai_recommendation' => 'Illegal auto execution',
            'is_auto_executed' => true, // Discrepancy!
            'human_decision' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
