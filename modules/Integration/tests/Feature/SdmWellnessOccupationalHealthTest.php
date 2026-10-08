<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\SdmWellnessOccupationalHealthService;
use Tests\TestCase;

class SdmWellnessOccupationalHealthTest extends TestCase
{
    use RefreshDatabase;

    protected SdmWellnessOccupationalHealthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SdmWellnessOccupationalHealthService::class);
    }

    public function test_occupational_surveillance_record_encryption_and_segregation(): void
    {
        // 1. Record exam with encrypted vault token and zero HR exposure (285.1 & 285.4)
        $exam = $this->service->recordSurveillanceExam(
            surveillanceCode: 'SURV-MINING-2026-01',
            workerId: 'WORKER-MINER-101',
            environment: 'MINING_UNDERGROUND',
            rawMedicalRecordData: 'Spirometry Lung FEV1: 85%, Audiometry: Normal',
            fitnessStatus: 'FIT'
        );

        $this->assertEquals('FIT', $exam->fitness_for_duty_status);
        $this->assertFalse((bool) $exam->medical_details_exposed_to_hr); // Segregated from HR!
        $this->assertStringStartsWith('VAULT-MED-', $exam->encrypted_medical_vault_token);
    }

    public function test_worker_exam_refusal_procedural_handling(): void
    {
        // Employee refuses examination -> marked TEMPORARILY_UNFIT without forcing invasive exam (285.5 Edge Case)
        $refusal = $this->service->handleExamRefusal('WORKER-SMELTER-202', 'SMELTER_FURNACE');

        $this->assertEquals('TEMPORARILY_UNFIT', $refusal->fitness_for_duty_status);
        $this->assertTrue((bool) $refusal->refused_examination);
        $this->assertFalse((bool) $refusal->medical_details_exposed_to_hr);
    }

    public function test_eap_counseling_complete_anonymity(): void
    {
        // EAP session recorded with individual PII omitted (285.2 & 285.6)
        $eap = $this->service->recordAnonymousEapCase(
            departmentCode: 'CUSTOMER_OPERATIONS',
            counselingCategory: 'STRESS_BURNOUT',
            referralCompleted: true
        );

        $this->assertTrue((bool) $eap->individual_pii_omitted);
        $this->assertStringStartsWith('EAP-ANON-', $eap->session_anon_token);
    }

    public function test_ergonomics_measurable_incident_reduction(): void
    {
        // Measured musculoskeletal incident reduction (from 20 down to 4 => 80% reduction) (285.3 & 285.7)
        // 16 incidents avoided * $5,000 = $80,000 avoided cost
        $ergo = $this->service->evaluateErgonomicsProgram(
            programCode: 'ERGO-SORONG-DC-01',
            siteCode: 'DC-SORONG-LOGISTICS',
            preInterventionIncidents: 20,
            postInterventionIncidents: 4,
            costPerIncidentUsd: 5000.0
        );

        $this->assertEquals(80.0, (float) $ergo->incident_reduction_pct);
        $this->assertEquals(80000.0, (float) $ergo->avoided_cost_usd);
    }

    public function test_sdm_wellness_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordSurveillanceExam('SURV-AUD', 'W1', 'MINING', 'DATA');
        $this->service->handleExamRefusal('W2', 'SMELTER');
        $this->service->recordAnonymousEapCase('DEPT', 'STRESS');
        $this->service->evaluateErgonomicsProgram('ERGO-AUD', 'SITE', 10, 2);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: medical record details exposed to HR
        DB::table('sdm_occupational_health_surveillances')->insert([
            'surveillance_code' => 'SURV-LEAKED-HR',
            'worker_id' => 'WORKER-LEAKED',
            'high_risk_environment' => 'MINING',
            'encrypted_medical_vault_token' => 'VAULT-LEAKED',
            'fitness_for_duty_status' => 'FIT',
            'medical_details_exposed_to_hr' => true, // Discrepancy!
            'refused_examination' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
