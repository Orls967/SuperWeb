<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CxMetricsVocOrchestrationService;
use Tests\TestCase;

class CxMetricsVocOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected CxMetricsVocOrchestrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CxMetricsVocOrchestrationService::class);
    }

    public function test_voc_feedback_detractor_auto_case_opening_and_resolution(): void
    {
        // 1. Promoter feedback (NPS = 10) -> no remediation case required
        $promoter = $this->service->recordVocFeedback(
            customerId: 'CUST-HAPPY-01',
            businessLine: 'HOTEL',
            npsScore: 10,
            sentiment: 'POSITIVE',
            theme: 'STAFF_COURTESY'
        );
        $this->assertFalse((bool) $promoter->closed_loop_case_opened);
        $this->assertEquals('NOT_REQUIRED', $promoter->closed_loop_status);

        // 2. Detractor feedback (NPS = 3 <= 6) -> auto opens closed-loop case (250.1 & 250.6 Edge Case)
        $detractor = $this->service->recordVocFeedback(
            customerId: 'CUST-UPSET-02',
            businessLine: 'HOSPITAL',
            npsScore: 3,
            sentiment: 'NEGATIVE',
            theme: 'SERVICE_SPEED'
        );
        $this->assertTrue((bool) $detractor->closed_loop_case_opened);
        $this->assertEquals('OPEN', $detractor->closed_loop_status);

        // 3. Resolve closed-loop case (250.5)
        $resolved = $this->service->resolveClosedLoopCase((int) $detractor->id, 'Patient contacted, wait time compensated.');
        $this->assertEquals('RESOLVED', $resolved->closed_loop_status);
    }

    public function test_digital_journey_step_drop_off_instrumentation(): void
    {
        // Journey: Hospital care path (250.2 & 250.5)
        $step = $this->service->recordJourneyStepMetrics(
            journeyCode: 'JOURNEY_HEAL',
            stepName: 'TELEMEDICINE_TRIAGE',
            visitorsCount: 1000,
            dropOffCount: 150
        );

        $this->assertEquals('JOURNEY_HEAL', $step->journey_code);
        $this->assertEquals(150, (int) $step->drop_off_count);
        $this->assertEquals(15.00, (float) $step->drop_off_rate_pct);
    }

    public function test_personalization_privacy_consent_and_frequency_caps(): void
    {
        // 1. Allowed with consent & under frequency cap (250.3 & 250.5)
        $interaction = $this->service->deliverPersonalizedInteraction(
            customerId: 'CUST-VIP-01',
            touchpoint: 'MOBILE_APP',
            messagePayload: 'Exclusive weekend getaway discount',
            userConsentGranted: true,
            recentInteractionsToday: 1
        );
        $this->assertNotNull($interaction);

        // 2. Rejected without user consent (250.3 & 250.5)
        try {
            $this->service->deliverPersonalizedInteraction(
                customerId: 'CUST-NO-CONSENT',
                touchpoint: 'EMAIL',
                messagePayload: 'Unsolicited offer',
                userConsentGranted: false,
                recentInteractionsToday: 0
            );
            $this->fail('Expected exception when user consent is not granted');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('privacy consent not granted', $e->getMessage());
        }

        // 3. Suppressed when frequency cap is exceeded (>= 3)
        $this->expectException(InvalidArgumentException::class);
        $this->service->deliverPersonalizedInteraction(
            customerId: 'CUST-SPAMMED',
            touchpoint: 'SMS',
            messagePayload: 'Another message',
            userConsentGranted: true,
            recentInteractionsToday: 3
        );
    }

    public function test_statistical_cx_financial_correlation_with_disclaimer(): void
    {
        // NPS vs Annual Spend data points (250.4 & 250.7)
        $npsScores = [2.0, 4.0, 6.0, 8.0, 10.0];
        $annualSpend = [500.0, 900.0, 1400.0, 2100.0, 3000.0];

        $corr = $this->service->calculateFinancialCorrelation(
            metricPair: 'NPS_VS_ANNUAL_SPEND',
            xValues: $npsScores,
            yValues: $annualSpend
        );

        $this->assertEquals('NPS_VS_ANNUAL_SPEND', $corr->metric_pair);
        $this->assertGreaterThan(0.9, (float) $corr->pearson_r_coefficient); // Strong linear association
        $this->assertTrue((bool) $corr->is_correlation_explicitly_labeled);
        // Explicit non-causation disclaimer verified (250.7)
        $this->assertStringContainsString('Correlation does not imply causation', $corr->disclaimer_text);
    }

    public function test_cx_orchestration_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordVocFeedback('CUST-AUD-1', 'CAMPUS', 9, 'POSITIVE', 'QUALITY');
        $this->service->recordJourneyStepMetrics('JOURNEY_BUY', 'CHECKOUT', 500, 20);
        $this->service->deliverPersonalizedInteraction('CUST-AUD-1', 'WEB_PORTAL', 'Hello', true, 0);
        $this->service->calculateFinancialCorrelation('TEST', [1.0, 2.0], [10.0, 20.0]);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: neglected detractor without opened case
        DB::table('cx_voc_feedback')->insert([
            'feedback_code' => 'VOC-NEGLECTED',
            'customer_id' => 'ANGRY-USER',
            'business_line' => 'ENTERTAINMENT',
            'nps_score' => 1,
            'sentiment' => 'NEGATIVE',
            'theme' => 'PRICING',
            'closed_loop_case_opened' => false, // Discrepancy!
            'closed_loop_status' => 'NOT_REQUIRED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
