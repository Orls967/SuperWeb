<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EsgAssuranceDisclosureControlService;
use Tests\TestCase;

class EsgAssuranceDisclosureControlTest extends TestCase
{
    use RefreshDatabase;

    protected EsgAssuranceDisclosureControlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EsgAssuranceDisclosureControlService::class);
    }

    public function test_evidence_gate_and_restatement_process_preserving_prior_publication(): void
    {
        // 1. Publishing without supporting evidence is strictly blocked (331.2 & 331.4)
        try {
            $this->service->publishDisclosure(
                publicationCode: 'PUB-EMISSIONS-UNSUPPORTED',
                reportingYear: '2026',
                metricName: 'SCOPE_1_GHG_EMISSIONS',
                reportedValue: 500000.0,
                dataTier: 'EMPIRICALLY_MEASURED',
                hasEvidence: false
            );
            $this->fail('Expected exception for unevidenced disclosure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unsubstantiated metric value lacking source evidence cannot be published', $e->getMessage());
        }

        // 2. Publication with verified evidence succeeds (331.1 & 331.4)
        $pub1 = $this->service->publishDisclosure(
            publicationCode: 'PUB-2026-SCOPE1-ORIGINAL',
            reportingYear: '2026',
            metricName: 'SCOPE_1_GHG_EMISSIONS',
            reportedValue: 450000.0,
            dataTier: 'EMPIRICALLY_MEASURED',
            hasEvidence: true
        );
        $this->assertEquals(450000.0, (float) $pub1->reported_value);
        $this->assertFalse((bool) $pub1->is_restated);

        // 3. Material error discovered triggers formal restatement preserving old record (331.1, 331.4, 331.5 Edge Case)
        $restatedPub = $this->service->restateDisclosure(
            priorCode: 'PUB-2026-SCOPE1-ORIGINAL',
            newCode: 'PUB-2026-SCOPE1-RESTATED-R1',
            correctedValue: 472000.0,
            reason: 'Audit correction on downstream smelter diesel fuel meters'
        );
        $this->assertEquals(472000.0, (float) $restatedPub->reported_value);
        $this->assertEquals('PUB-2026-SCOPE1-ORIGINAL', $restatedPub->previous_publication_code);

        // Check original publication is marked restated
        $orig = DB::table('esg_disclosure_publications')->where('publication_code', 'PUB-2026-SCOPE1-ORIGINAL')->first();
        $this->assertTrue((bool) $orig->is_restated);
    }

    public function test_green_capex_reconciliation_with_financial_gl(): void
    {
        // 1. Matched capex reconciles (331.3 & 331.4)
        $packMatched = $this->service->reconcileGreenCapexWithFinance(
            packCode: 'PACK-2026-AUDITED',
            reportingYear: '2026',
            greenCapexReported: 50000000.0,
            glCapexAudited: 50000000.0
        );
        $this->assertTrue((bool) $packMatched->financial_audit_reconciled);
        $this->assertEquals(0.00, (float) $packMatched->variance_usd);

        // 2. Mismatched capex flags discrepancy
        $packMismatched = $this->service->reconcileGreenCapexWithFinance(
            packCode: 'PACK-2026-VARIANCE',
            reportingYear: '2026',
            greenCapexReported: 52000000.0,
            glCapexAudited: 50000000.0
        );
        $this->assertFalse((bool) $packMismatched->financial_audit_reconciled);
        $this->assertEquals(2000000.0, (float) $packMismatched->variance_usd);
    }

    public function test_esg_assurance_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->publishDisclosure('PUB-AUD', '2026', 'METRIC', 100.0, 'EMPIRICALLY_MEASURED', true);
        $this->service->reconcileGreenCapexWithFinance('PACK-AUD', '2026', 1000.0, 1000.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: published without evidence
        DB::table('esg_disclosure_publications')->insert([
            'publication_code' => 'PUB-DEFECT-UNVERIFIED',
            'reporting_year' => '2026',
            'metric_name' => 'WATER_INTENSITY',
            'reported_value' => 50.0,
            'data_tier' => 'EMPIRICALLY_MEASURED',
            'has_supporting_evidence' => false, // Discrepancy!
            'is_restated' => false,
            'previous_publication_code' => null,
            'published_to_board' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
