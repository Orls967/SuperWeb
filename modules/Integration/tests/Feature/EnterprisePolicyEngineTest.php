<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterprisePolicyEngineService;
use Tests\TestCase;

class EnterprisePolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    protected EnterprisePolicyEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterprisePolicyEngineService::class);
    }

    public function test_policy_simulation_gate_and_activation(): void
    {
        // 1. Register policy (290.1)
        $this->service->registerPolicy('POL-PRICE-FLOOR-01', 'PRICING', 'CONTRACT_SPECIFIC');

        // 2. Untested policy cannot activate (290.5)
        try {
            $this->service->activatePolicy('POL-PRICE-FLOOR-01');
            $this->fail('Expected exception for untested policy activation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('has not passed required pre-activation simulation tests', $e->getMessage());
        }

        // 3. Simulate and pass -> activation succeeds (290.1 & 290.5)
        $this->service->simulatePolicyTest('POL-PRICE-FLOOR-01', true);
        $activePolicy = $this->service->activatePolicy('POL-PRICE-FLOOR-01');
        $this->assertTrue((bool) $activePolicy->is_active);
    }

    public function test_runtime_precedence_and_ambiguity_fail_closed(): void
    {
        $this->service->registerPolicy('POL-EXPORT-BAN', 'SAFETY', 'REGIONAL_LAW');
        $this->service->simulatePolicyTest('POL-EXPORT-BAN', true);
        $this->service->activatePolicy('POL-EXPORT-BAN');

        // 1. Normal unambiguous policy decision allowed (290.2)
        $normalDec = $this->service->evaluatePolicyDecision('POL-EXPORT-BAN', ['commodity' => 'NICKEL_ORE', 'dest' => 'DOMESTIC']);
        $this->assertEquals('ALLOW', $normalDec->decision_outcome);

        // 2. Ambiguous conflict between conflicting regional and global rules strictly fails-closed (DENY) (290.4 & 290.6 Edge Case)
        $ambiguousDec = $this->service->evaluatePolicyDecision(
            'POL-EXPORT-BAN',
            ['commodity' => 'NICKEL_ORE', 'dest' => 'OVERSEAS_FREEPORT'],
            hasAmbiguousConflict: true
        );
        $this->assertEquals('DENY_AMBIGUOUS_FAIL_CLOSED', $ambiguousDec->decision_outcome);
    }

    public function test_breakglass_emergency_override_guards_and_post_review(): void
    {
        $this->service->registerPolicy('POL-CREDIT-LIMIT', 'CREDIT');

        // 1. Overriding core ledger invariant is strictly blocked at architectural level (290.3 & 290.8)
        try {
            $this->service->requestBreakglassOverride('POL-CREDIT-LIMIT', 'ADMIN_1', 'ADMIN_2', 30, attemptLedgerBypass: true);
            $this->fail('Expected exception for ledger invariant bypass attempt');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot bypass core financial ledger invariants', $e->getMessage());
        }

        // 2. Single approval without secondary approver rejected (290.3)
        try {
            $this->service->requestBreakglassOverride('POL-CREDIT-LIMIT', 'ADMIN_1', null, 30);
            $this->fail('Expected exception for missing secondary approver');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Emergency override requires two distinct authorized approvers', $e->getMessage());
        }

        // 3. Valid dual-approved break-glass succeeds with auto-expiry and post-review completion (290.3 & 290.7)
        $override = $this->service->requestBreakglassOverride('POL-CREDIT-LIMIT', 'ADMIN_1', 'ADMIN_2', 45);
        $this->assertNotNull($override->override_token);
        $this->assertFalse((bool) $override->post_incident_reviewed);

        $reviewed = $this->service->reviewBreakglassOverride($override->override_token);
        $this->assertTrue((bool) $reviewed->post_incident_reviewed);
    }

    public function test_enterprise_policy_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerPolicy('POL-AUD', 'DOMAIN');
        $this->service->simulatePolicyTest('POL-AUD', true);
        $this->service->activatePolicy('POL-AUD');
        $this->service->evaluatePolicyDecision('POL-AUD', ['key' => 'val']);
        $ovr = $this->service->requestBreakglassOverride('POL-AUD', 'USER_1', 'USER_2', 60);
        $this->service->reviewBreakglassOverride($ovr->override_token);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: active policy that did not pass simulation test
        DB::table('gov_policy_catalog')->insert([
            'policy_code' => 'POL-ROGUE-UNTESTED',
            'policy_domain' => 'PRICING',
            'version' => 1,
            'precedence_tier' => 'GLOBAL_DEFAULT',
            'simulation_test_passed' => false,
            'is_active' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
