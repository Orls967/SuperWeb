<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseAiGovernanceOperatingModelService;
use Tests\TestCase;

class EnterpriseAiGovernanceOperatingModelTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseAiGovernanceOperatingModelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseAiGovernanceOperatingModelService::class);
    }

    public function test_shadow_model_remediation_and_blocking_edge_case(): void
    {
        // 1. Shadow model registered triggers remediation and blocks inference (360.5 Edge Case)
        $shadow = $this->service->registerModelInventory(
            modelCode: 'MODEL-SHADOW-NLP-BOT',
            modelName: 'Unapproved Departmental NLP Bot',
            ownerDomain: 'COMMERCE',
            riskClassification: 'HIGH_RISK',
            attestationExpiry: Carbon::now()->addMonths(6),
            isShadow: true // Shadow model!
        );
        $this->assertTrue((bool) $shadow->is_shadow_unregistered);
        $this->assertTrue((bool) $shadow->remediation_in_progress);
        $this->assertFalse((bool) $shadow->inference_permitted);

        // Attempting inference validation fails (360.5)
        try {
            $this->service->validateInferenceEligibility('MODEL-SHADOW-NLP-BOT');
            $this->fail('Expected exception for uncataloged shadow model inference');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Shadow model \'MODEL-SHADOW-NLP-BOT\' must complete formal remediation', $e->getMessage());
        }
    }

    public function test_annual_attestation_expiry_blocks_high_risk_inference(): void
    {
        // 1. Expired high risk model blocks inference (360.2 & 360.4)
        $this->service->registerModelInventory(
            modelCode: 'MODEL-CREDIT-SCORING-V1',
            modelName: 'Commercial Credit Scoring AI',
            ownerDomain: 'FINANCE',
            riskClassification: 'HIGH_RISK',
            attestationExpiry: Carbon::now()->subDays(10), // Expired 10 days ago!
            isShadow: false
        );

        try {
            $this->service->validateInferenceEligibility('MODEL-CREDIT-SCORING-V1');
            $this->fail('Expected exception for expired attestation high-risk model');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Annual attestation expired: High-risk model inference is blocked', $e->getMessage());
        }

        // 2. Active attestation succeeds (360.2 & 360.4)
        $this->service->registerModelInventory(
            modelCode: 'MODEL-CREDIT-SCORING-V2',
            modelName: 'Commercial Credit Scoring AI v2',
            ownerDomain: 'FINANCE',
            riskClassification: 'HIGH_RISK',
            attestationExpiry: Carbon::now()->addYear(),
            isShadow: false
        );

        $valid = $this->service->validateInferenceEligibility('MODEL-CREDIT-SCORING-V2');
        $this->assertTrue((bool) $valid->inference_permitted);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerModelInventory('M-AUD', 'Name', 'MINING', 'LOW_RISK', Carbon::now()->addYear(), false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: shadow model permitted for inference
        DB::table('ai_governance_operating_inventories')->insert([
            'model_code' => 'M-DEFECT-SHADOW',
            'model_name' => 'Defect',
            'owner_domain' => 'MINING',
            'risk_classification' => 'HIGH_RISK',
            'is_shadow_unregistered' => true, // Discrepancy!
            'remediation_in_progress' => false,
            'attestation_expires_at' => Carbon::now()->addYear(),
            'inference_permitted' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
