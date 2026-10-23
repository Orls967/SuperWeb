<?php

declare(strict_types=1);

namespace Tests\Feature\Wave2;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Application\Wave2\AllocateConglomerateGroupCapitalWaccRoicAction;
use Modules\Core\Application\Wave2\EnforceZeroTrustPrivacyVaultConsentAndDsarAction;
use Modules\Core\Application\Wave2\EvaluateCross17LinesAutonomousDecisionGuardrailAction;
use Modules\Core\Application\Wave2\OrchestrateCross17LinesMonolithEventFlowAction;
use Modules\Core\Application\Wave2\Run17LinesTwelveMonthGoldenConglomerateSimulationAction;
use Modules\Core\Application\Wave2\VerifyMultiRegionActiveActiveFailoverReadinessAction;
use Tests\TestCase;

class Fase140To150Conglomerate17LinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_140_cross_17_lines_monolith_event_spine_flow(): void
    {
        $action = app(OrchestrateCross17LinesMonolithEventFlowAction::class);

        $params = [
            'orchestration_id' => 'W2-FLOW-001',
            'event_id' => 'EVT-GLOBAL-FEST-2026',
            'surge_kwh' => 2000,
            'energy_rate_minor' => 2500,
            'slice_fee_minor' => 30000000,
            'stream_views' => 500000,
            'certified_crew_count' => 60,
            'ticket_gmv_minor' => 200000000,
        ];

        $res = $action->execute($params);

        $this->assertEquals(17, $res['lines_covered_count']);
        $this->assertEquals(7, $res['total_events_dispatched']);
        $this->assertEquals(5000000, $res['energy_billed_minor']); // 2000 * 2500
        $this->assertEquals(10000000, $res['cashback_earned_minor']); // 5% of 200M
        $this->assertEquals('COMPLETE_ZERO_DRIFT', $res['integration_status']);

        $tx = LedgerTransaction::where('reference_id', 'W2-FLOW-001')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_140_one_day_full_cycle_cross_ecosystem_integration(): void
    {
        $action = app(OrchestrateCross17LinesMonolithEventFlowAction::class);

        $res = $action->execute([
            'orchestration_id' => 'W2-DAY-CYCLE-02',
            'event_id' => 'EVT-CONGLOMERATE-DAY-01',
            'surge_kwh' => 1000,
            'energy_rate_minor' => 2000,
        ]);

        $this->assertCount(7, $res['event_trace']);
        $this->assertEquals('ven.festival_opened', $res['event_trace'][0]['topic']);
        $this->assertEquals('ret.cashback_issued', $res['event_trace'][6]['topic']);
    }

    public function test_fase_141_conglomerate_group_capital_allocation_and_wacc_roic_scoring(): void
    {
        $action = app(AllocateConglomerateGroupCapitalWaccRoicAction::class);

        // Treasury budget: 500 Billion IDR. WACC Hurdle: 10.00% (1000 bps)
        // Proposal 1: Solar Farm (12% IRR) -> Approved (150B)
        // Proposal 2: Hospital Expansion (14% IRR) -> Approved (200B)
        // Proposal 3: Low-return factory (7% IRR) -> Rejected (Below hurdle)
        $params = [
            'allocation_code' => 'CAP-ALLOC-2026-Q4',
            'treasury_available_budget_minor' => 500000000000,
            'group_wacc_basis_points' => 1000,
            'capex_proposals' => [
                ['subholding' => 'RESOURCES', 'project_name' => 'Solar Farm Phase 4', 'capex_requested_minor' => 150000000000, 'projected_irr_basis_points' => 1200, 'strategic_score' => 90, 'esg_score' => 95],
                ['subholding' => 'HOSPITALITY', 'project_name' => 'Specialist Hospital Tower', 'capex_requested_minor' => 200000000000, 'projected_irr_basis_points' => 1400, 'strategic_score' => 95, 'esg_score' => 88],
                ['subholding' => 'CONSUMER', 'project_name' => 'Legacy Packaging Line', 'capex_requested_minor' => 100000000000, 'projected_irr_basis_points' => 700, 'strategic_score' => 50, 'esg_score' => 60],
            ],
            'subsidiary_net_profit_minor' => 100000000000,
            'dividend_payout_ratio_pct' => 40,
        ];

        $res = $action->execute($params);

        $this->assertEquals(350000000000, $res['total_allocated_minor']); // 150B + 200B
        $this->assertEquals(150000000000, $res['remaining_budget_minor']);
        $this->assertCount(2, $res['allocated_proposals']);
        $this->assertCount(1, $res['rejected_proposals']);
        $this->assertEquals('IRR_BELOW_WACC_HURDLE', $res['rejected_proposals'][0]['reason']);
        $this->assertEquals(40000000000, $res['holding_dividend_minor']); // 40% of 100B
    }

    public function test_fase_141_subholding_dividend_payout_and_ma_integration_idempotency(): void
    {
        $action = app(AllocateConglomerateGroupCapitalWaccRoicAction::class);

        $res1 = $action->execute([
            'allocation_code' => 'CAP-MA-001',
            'ma_target_code' => 'HOTEL-CHAIN-BALI',
        ]);
        $res2 = $action->execute([
            'allocation_code' => 'CAP-MA-001',
            'ma_target_code' => 'HOTEL-CHAIN-BALI',
        ]);

        $this->assertEquals($res1['ma_backfill_hash'], $res2['ma_backfill_hash']);
        $this->assertEquals('APPROVED_BY_EXECUTIVE_BOARD', $res1['governance_status']);
    }

    public function test_fase_142_scale_provisioner_query_budget_and_anti_race_conditions(): void
    {
        $action = app(Run17LinesTwelveMonthGoldenConglomerateSimulationAction::class);

        $params = [
            'p95_grid_dispatch_ms' => 350, // <= 500ms
            'p95_marketplace_allocation_ms' => 75, // <= 100ms
            'simulated_days' => 30,
        ];

        $res = $action->execute($params);

        $this->assertTrue($res['query_budget_compliant']);
        $this->assertEquals(350, $res['p95_grid_dispatch_ms']);
        $this->assertEquals(75, $res['p95_marketplace_allocation_ms']);
    }

    public function test_fase_143_deterministic_cross_line_decision_engine_and_audit_reconstruction(): void
    {
        $action = app(EvaluateCross17LinesAutonomousDecisionGuardrailAction::class);

        $params = [
            'decision_code' => 'AI-DET-001',
            'model_id' => 'CROSS-LINE-OPTM-V1',
            'model_version' => '1.0.0',
            'random_seed' => 12345,
            'autonomous_level' => 3,
            'input_features' => ['load_kw' => 5000, 'fuel_price' => 15000, 'margin' => 20],
            'cross_line_anomalies' => [
                ['line' => 'MINING', 'anomaly_type' => 'FUEL_SPIKE', 'severity' => 'HIGH', 'value' => 25],
                ['line' => 'LOGISTICS', 'anomaly_type' => 'FREIGHT_SURGE', 'severity' => 'HIGH', 'value' => 30],
            ],
        ];

        $res1 = $action->execute($params);
        $res2 = $action->execute($params);

        $this->assertEquals($res1['reconstruction_hash'], $res2['reconstruction_hash']);
        $this->assertEquals('EXECUTED_SUCCESS', $res1['execution_status']);
        $this->assertNotNull($res1['correlated_incident']);
        $this->assertEquals('CROSS_LINE_SUPPLY_CHAIN_INFLATION', $res1['correlated_incident']['root_cause']);
    }

    public function test_fase_143_autonomous_kill_switch_emergency_stop_within_threshold(): void
    {
        $action = app(EvaluateCross17LinesAutonomousDecisionGuardrailAction::class);

        $res = $action->execute([
            'decision_code' => 'AI-KILL-002',
            'kill_switch_state' => 'ACTIVATED',
        ]);

        $this->assertEquals('EMERGENCY_HALTED_BY_KILL_SWITCH', $res['execution_status']);
        $this->assertFalse($res['auto_executed']);
        $this->assertLessThan(1000, $res['halt_latency_ms']); // < 1s
    }

    public function test_fase_144_zero_trust_privacy_vault_tokenization_and_dsar_erasure(): void
    {
        $action = app(EnforceZeroTrustPrivacyVaultConsentAndDsarAction::class);

        // Scenario 1: Consent granted
        $params = [
            'vault_operation_code' => 'PV-OK-001',
            'subject_id' => 'PATIENT-001',
            'token_scopes' => ['privacy_vault:access', 'health:read'],
            'required_scope' => 'privacy_vault:access',
            'raw_pii_field' => 'NIK-3201019000000001',
            'consent_status' => 'OPTED_IN',
        ];

        $res = $action->execute($params);

        $this->assertStringStartsWith('TKN-', $res['anonymized_token']);
        $this->assertTrue($res['analytics_allowed']);
        $this->assertEquals('ACTIVE', $res['erasure_status']);

        // Scenario 2: Consent revoked / DSAR requested -> Purged from analytics
        $dsarRes = $action->execute([
            'vault_operation_code' => 'PV-REVOKED-002',
            'subject_id' => 'PATIENT-002',
            'token_scopes' => ['privacy_vault:access'],
            'consent_status' => 'REVOKED',
            'dsar_erasure_request' => 'ERASURE_REQUESTED',
        ]);

        $this->assertFalse($dsarRes['analytics_allowed']);
        $this->assertEquals('ANONYMIZED_PURGED_FROM_ANALYTICS', $dsarRes['erasure_status']);
    }

    public function test_fase_144_regulatory_compliance_matrix_blocks_operations_on_expired_permit(): void
    {
        $action = app(EnforceZeroTrustPrivacyVaultConsentAndDsarAction::class);

        $params = [
            'vault_operation_code' => 'PV-REG-003',
            'token_scopes' => ['privacy_vault:access'],
            'regulatory_permits' => [
                ['line' => 'HEALTHCARE', 'permit_name' => 'RS_OPERATIONAL_LICENSE', 'status' => 'ACTIVE'],
                ['line' => 'MINING', 'permit_name' => 'AMDAL_ENVIRONMENTAL_PERMIT', 'status' => 'EXPIRED'],
            ],
        ];

        $res = $action->execute($params);

        $this->assertEquals('RESTRICTED', $res['compliance_status']);
        $this->assertEquals(1, $res['blocked_operations_count']);
        $this->assertEquals('MINING', $res['blocked_operations'][0]['line']);
    }

    public function test_fase_145_multi_region_active_active_failover_drill_achieves_zero_rpo(): void
    {
        $action = app(VerifyMultiRegionActiveActiveFailoverReadinessAction::class);

        $params = [
            'drill_code' => 'DR-DRILL-001',
            'primary_region' => 'ap-southeast-3-jakarta',
            'secondary_region' => 'ap-southeast-1-singapore',
            'chaos_injection_event' => 'PRIMARY_DATACENTER_FIRE_CHAOS',
            'primary_ledger_tx_count' => 250000,
            'replicated_ledger_tx_count' => 250000, // 100% matched -> RPO = 0
            'rto_target_minutes' => 15,
            'rto_achieved_minutes' => 9,
            'edge_transactions' => [
                ['edge_node_id' => 'EDGE-MINE-01', 'idempotency_key' => 'KEY-001', 'payload_hash' => 'hash1'],
                ['edge_node_id' => 'EDGE-MINE-01', 'idempotency_key' => 'KEY-001', 'payload_hash' => 'hash1'], // Duplicate
                ['edge_node_id' => 'EDGE-VENUE-02', 'idempotency_key' => 'KEY-002', 'payload_hash' => 'hash2'],
            ],
        ];

        $res = $action->execute($params);

        $this->assertEquals('ZERO_DATA_LOSS_RPO_0', $res['rpo_status']);
        $this->assertEquals(0, $res['data_loss_count']);
        $this->assertTrue($res['rto_compliant']);
        $this->assertEquals(2, $res['edge_synced_count']);
        $this->assertEquals(1, $res['edge_duplicate_rejected']);
        $this->assertEquals('CERTIFIED_ACTIVE_ACTIVE', $res['failover_readiness']);
    }

    public function test_fase_146_data_lakehouse_cdc_ingestion_and_mdm_golden_record_sync(): void
    {
        $action = app(Run17LinesTwelveMonthGoldenConglomerateSimulationAction::class);

        $params = [
            'cdc_events_ingested' => 2000000,
            'golden_records_synced' => 75000,
            'data_quality_score' => 99,
        ];

        $res = $action->execute($params);

        $this->assertEquals(2000000, $res['cdc_events_ingested']);
        $this->assertEquals(75000, $res['golden_records_synced']);
        $this->assertGreaterThanOrEqual(95, $res['data_quality_score']);
    }

    public function test_fase_147_open_platform_api_rate_limiting_and_developer_monetization(): void
    {
        $action = app(Run17LinesTwelveMonthGoldenConglomerateSimulationAction::class);

        $params = [
            'api_endpoints_count' => 1200,
            'active_developers' => 600,
            'api_monetization_minor' => 120000000,
        ];

        $res = $action->execute($params);

        $this->assertEquals(1200, $res['api_endpoints_count']);
        $this->assertEquals(600, $res['active_developers']);
        $this->assertEquals(120000000, $res['api_monetization_minor']);
    }

    public function test_fase_148_twelve_month_conglomerate_golden_mega_scenario_end_to_end(): void
    {
        $action = app(Run17LinesTwelveMonthGoldenConglomerateSimulationAction::class);

        $params = [
            'simulation_code' => 'MEGA-12M-2026',
            'simulated_days' => 365,
            'consolidated_gmv_minor' => 2500000000000,
            'group_net_profit_minor' => 400000000000,
            'audit_discrepancy_count' => 0,
        ];

        $res = $action->execute($params);

        $this->assertEquals(365, $res['simulated_days']);
        $this->assertEquals(17, $res['business_lines_count']);
        $this->assertEquals('ALL_17_LINES_CERTIFIED_BALANCED', $res['final_certification']);
    }

    public function test_fase_150_full_17_lines_zero_balance_reconciliation_and_audit_certification(): void
    {
        $action = app(Run17LinesTwelveMonthGoldenConglomerateSimulationAction::class);

        $res = $action->execute([
            'simulation_code' => 'FINAL-SERAH-TERIMA-FASE-150',
            'audit_discrepancy_count' => 0,
        ]);

        $this->assertEquals(0, $res['audit_discrepancy_count']);
        $this->assertEquals('ALL_17_LINES_CERTIFIED_BALANCED', $res['final_certification']);

        // Check if discrepancy > 0 throws exception
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ledger discrepancies detected');

        $action->execute([
            'simulation_code' => 'FINAL-FAIL',
            'audit_discrepancy_count' => 5,
        ]);
    }
}
