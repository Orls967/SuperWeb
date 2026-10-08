<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EcosystemHealthNetworkGovernanceService;
use Tests\TestCase;

class EcosystemHealthNetworkGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EcosystemHealthNetworkGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EcosystemHealthNetworkGovernanceService::class);
    }

    public function test_fair_access_ranking_and_self_preferencing_rejection(): void
    {
        // 1. Attempting self-preferencing boost for platform-owned item is blocked (375.2 & 375.4)
        try {
            $this->service->computeFairRanking(
                rankingCode: 'RNK-TIRE-001',
                itemCode: 'ITEM-AUTOSERVE-TIRE-PRO',
                isPlatformOwned: true,
                organicScore: 88.5,
                forceSelfPreferencingBoost: true // Illegal boost!
            );
            $this->fail('Expected exception for self-preferencing ranking boost');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Self-preferencing boosts for platform items strictly prohibited', $e->getMessage());
        }

        // 2. Fair ranking produces reproducible organic score (375.2 & 375.4)
        $ranking = $this->service->computeFairRanking(
            rankingCode: 'RNK-TIRE-002',
            itemCode: 'ITEM-PARTNER-TIRE-MAX',
            isPlatformOwned: false,
            organicScore: 92.4,
            forceSelfPreferencingBoost: false
        );
        $this->assertEquals(92.4, $ranking->reproducible_rank_score);
        $this->assertFalse((bool) $ranking->self_preferencing_boost_applied);
    }

    public function test_network_health_intervention_capped_and_distortion_termination_edge_case(): void
    {
        // 1. Subsidy exceeding cap fails (375.3 & 375.4)
        try {
            $this->service->issueHealthIntervention(
                interventionCode: 'INTV-SUBSIDY-001',
                partnerCode: 'PTN-EXPEDITION-01',
                subsidyAmountUsd: 7500.00, // Exceeds 5000 cap!
                subsidyCapUsd: 5000.00,
                hasMarketDistortion: false
            );
            $this->fail('Expected exception for subsidy exceeding cap');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds maximum cap', $e->getMessage());
        }

        // 2. Intervention causing market distortion is immediately terminated (375.4 & 375.5 Edge Case)
        $distortedIntervention = $this->service->issueHealthIntervention(
            interventionCode: 'INTV-SUBSIDY-002',
            partnerCode: 'PTN-EXPEDITION-02',
            subsidyAmountUsd: 4000.00,
            subsidyCapUsd: 5000.00,
            hasMarketDistortion: true // Causes distortion!
        );
        $this->assertTrue((bool) $distortedIntervention->net_negative_market_distortion);
        $this->assertTrue((bool) $distortedIntervention->intervention_terminated);
    }

    public function test_ecosystem_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->computeFairRanking('R-AUD', 'ITEM-1', false, 80.0, false);
        $this->service->issueHealthIntervention('I-AUD', 'PTN-1', 1000.00, 5000.00, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: active distorting intervention not terminated
        DB::table('ecosystem_health_interventions')->insert([
            'intervention_code' => 'I-DEFECT-UNTERMINATED',
            'partner_code' => 'PTN-1',
            'subsidy_amount_usd' => 2000.00,
            'subsidy_cap_usd' => 5000.00,
            'net_negative_market_distortion' => true, // Discrepancy!
            'intervention_terminated' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
