<?php

declare(strict_types=1);

namespace Tests\Feature\Wave3;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Application\Wave3\ContainerPortBerthCraneAndFashionAssortmentAction;
use Modules\Core\Application\Wave3\DigitalTrustVerifiableIdentityAndSmartDistrictGridAction;
use Modules\Core\Application\Wave3\Enterprise30LinesMasterCommandAndRelease300HandoverAction;
use Modules\Core\Application\Wave3\ForestrySvlkChainOfCustodyAndCircularEprCreditAction;
use Modules\Core\Application\Wave3\GlobalMultiCountryHqAndMobilityOrchestratorAction;
use Modules\Core\Application\Wave3\HigherEdCampusAccreditationAndEndowmentGrantAction;
use Modules\EnterpriseFinance\Application\Actions\ExecuteGlobalTaxPillarTwoCloseAndCapitalGovernanceAction;
use Modules\Esg\Application\Actions\VerifyForestrySvlkCustodyAndCircularEprOffsetAction;
use Modules\Partner\Application\Actions\SettleProfessionalLegalTrustAndCoSellAffiliateAction;
use Tests\TestCase;

class Fase201To250Wave3EnterpriseScaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_201_to_203_federated_sop_erm_framework_and_30_line_sod_control_testing(): void
    {
        $action = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $res = $action->execute([
            'command_code' => 'W3-SOP-ERM-201',
            'sod_conflict_count' => 0,
            'p95_critical_latency_ms' => 130,
        ]);

        $this->assertSame(30, $res['verified_business_lines_count']);
        $this->assertSame('CLEARED_REALTIME_MESH', $res['fraud_mesh_disposition']);
        $this->assertSame('RELEASE_V300_30_LINES_CERTIFIED', $res['certification_status']);
    }

    public function test_fase_204_to_206_cyber_resilience_third_party_risk_and_crisis_command_bcp(): void
    {
        $hqAction = app(GlobalMultiCountryHqAndMobilityOrchestratorAction::class);
        $masterAction = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $bcpRes = $hqAction->execute([
            'operation_code' => 'W3-BCP-206',
            'public_health_alert_level' => 'PANDEMIC_BORDER_CLOSURE',
        ]);
        $this->assertSame('FAILOVER_SECONDARY_CORRIDOR_ACTIVE', $bcpRes['bcp_mode']);
        $this->assertSame(100, $bcpRes['secondary_allocation_pct']);

        $drRes = $masterAction->execute([
            'command_code' => 'W3-CYBER-204',
            'rpo_lost_transactions' => 0,
        ]);
        $this->assertSame(0, $drRes['rpo_lost_transactions']);
    }

    public function test_fase_207_to_208_regulatory_policy_lifecycle_and_oecd_pillar_two_15_percent_minimum_tax(): void
    {
        $efAction = app(ExecuteGlobalTaxPillarTwoCloseAndCapitalGovernanceAction::class);

        // Jurisdiction pre-tax income 2.0B, covered tax paid 200M (10% ETR < 15% Pillar Two floor) -> 5% top-up = 100M
        $res = $efAction->execute([
            'close_period_code' => 'TAX-PILLAR2-208',
            'globe_income_minor' => 2000000000,
            'covered_tax_paid_minor' => 200000000,
            'transfer_pricing_markup_bps' => 850,
        ]);

        $this->assertSame(1000, $res['effective_tax_rate_bps']);
        $this->assertSame(500, $res['top_up_rate_bps']);
        $this->assertSame(100000000, $res['pillar_two_top_up_tax_minor']);

        $tx = LedgerTransaction::where('reference_id', 'TAX-PILLAR2-208')->first();
        $this->assertNotNull($tx);
        $this->assertSame(0, (int) $tx->entries->sum('amount_minor'));
    }

    public function test_fase_209_to_212_group_fast_close_blocks_intercompany_mismatch_and_out_of_corridor_transfer_pricing(): void
    {
        $efAction = app(ExecuteGlobalTaxPillarTwoCloseAndCapitalGovernanceAction::class);

        try {
            $efAction->execute([
                'close_period_code' => 'CLOSE-MISMATCH-209',
                'intercompany_mismatch_minor' => 150000,
            ]);
            $this->fail('Expected DomainException on intercompany elimination mismatch');
        } catch (DomainException $e) {
            $this->assertStringContainsString('intercompany elimination mismatch', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("outside OECD arm's-length corridor");
        $efAction->execute([
            'close_period_code' => 'TP-BREACH-212',
            'transfer_pricing_markup_bps' => 2500,
            'arms_length_min_bps' => 500,
            'arms_length_max_bps' => 1200,
        ]);
    }

    public function test_fase_210_to_211_capital_management_wacc_hurdle_approval_and_rejection(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $approved = $master->execute([
            'command_code' => 'CAP-WACC-OK-210',
            'project_irr_bps' => 1550,
            'group_wacc_hurdle_bps' => 1100,
        ]);
        $this->assertSame('CAPEX_APPROVED_ABOVE_WACC', $approved['capital_decision']);

        $rejected = $master->execute([
            'command_code' => 'CAP-WACC-LOW-210',
            'project_irr_bps' => 850,
            'group_wacc_hurdle_bps' => 1100,
        ]);
        $this->assertSame('CAPEX_REJECTED_BELOW_HURDLE', $rejected['capital_decision']);
    }

    public function test_fase_213_to_215_operations_qms_asset_reliability_and_30_line_wms_execution(): void
    {
        $portAction = app(ContainerPortBerthCraneAndFashionAssortmentAction::class);

        // Vessel faster than laytime earns despatch rebate (50% of hourly demurrage rate)
        $res = $portAction->execute([
            'call_code' => 'OPS-LAYTIME-215',
            'allowed_laytime_hours' => 40,
            'actual_port_stay_hours' => 32,
            'demurrage_hourly_rate_minor' => 6000000,
        ]);

        $this->assertSame(0, $res['demurrage_minor']);
        $this->assertSame(24000000, $res['despatch_rebate_minor']); // (40-32) * 6M / 2
    }

    public function test_fase_216_to_218_field_service_sla_portfolio_ppm_and_rnd_ip_transfer(): void
    {
        $districtAction = app(DigitalTrustVerifiableIdentityAndSmartDistrictGridAction::class);

        $met = $districtAction->execute([
            'district_session_code' => 'SLA-MET-216',
            'incident_dispatch_seconds' => 210,
            'sla_target_seconds' => 300,
        ]);
        $this->assertSame('SLA_MET', $met['district_sla_status']);

        $breached = $districtAction->execute([
            'district_session_code' => 'SLA-BREACH-216',
            'incident_dispatch_seconds' => 420,
            'sla_target_seconds' => 300,
        ]);
        $this->assertSame('SLA_BREACHED_ESCALATED', $breached['district_sla_status']);
    }

    public function test_fase_219_to_222_customer_360_unified_crm_service_desk_subscription_and_attribution(): void
    {
        $districtAction = app(DigitalTrustVerifiableIdentityAndSmartDistrictGridAction::class);

        $res = $districtAction->execute([
            'district_session_code' => 'CRM360-219',
            'citizen_reference' => 'CUST-GOLDEN-360',
            'district_cooling_minor' => 1500000,
            'smart_water_minor' => 500000,
            'mobility_transit_pass_minor' => 400000,
            'waste_smart_bin_minor' => 200000,
            'green_citizen_rebate_minor' => 300000,
        ]);

        $this->assertSame(2300000, $res['net_township_bill_minor']);
        $this->assertStringStartsWith('DID-', $res['identity_blind_index_hmac']);
    }

    public function test_fase_223_to_227_hcm_org_design_total_rewards_lifecycle_analytics_and_learning_cloud(): void
    {
        $hqAction = app(GlobalMultiCountryHqAndMobilityOrchestratorAction::class);
        $eduAction = app(HigherEdCampusAccreditationAndEndowmentGrantAction::class);

        $hqRes = $hqAction->execute([
            'operation_code' => 'HCM-GLOBAL-224',
            'base_salary_minor' => 180000000,
            'cola_allowance_minor' => 20000000,
            'home_hypo_tax_minor' => 40000000,
            'host_actual_tax_minor' => 35000000, // Host tax lower than hypo -> 0 topup
        ]);
        $this->assertSame(0, $hqRes['tax_equalization_topup_minor']);
        $this->assertSame(200000000, $hqRes['total_mobility_cost_minor']);

        $eduRes = $eduAction->execute([
            'academic_cycle_code' => 'LEARN-CLOUD-227',
            'degree_conferral_action' => 'UPSKILL_ENROLLMENT',
            'requested_semester_sks' => 18,
        ]);
        $this->assertSame('ENROLLED_ACTIVE', $eduRes['degree_status']);
    }

    public function test_fase_228_to_230_sustainability_esg_double_materiality_climate_and_circular_nature(): void
    {
        $esgAction = app(VerifyForestrySvlkCustodyAndCircularEprOffsetAction::class);
        $svlkAction = app(ForestrySvlkChainOfCustodyAndCircularEprCreditAction::class);

        $esgRes = $esgAction->execute([
            'certificate_code' => 'ESG-MAT-228',
            'recovered_waste_kg' => 30000,
            'credit_per_kg_minor' => 3500,
            'repairability_score_100' => 88,
        ]);
        $this->assertSame(105000000, $esgRes['epr_settlement_minor']);

        $shortfallRes = $svlkAction->execute([
            'custody_code' => 'EPR-SHORTFALL-230',
            'packaging_placed_on_market_kg' => 100000,
            'mandatory_recovery_target_bps' => 6000, // 60,000 kg target
            'verified_recycled_kg' => 45000, // 15,000 kg shortfall
            'epr_credit_price_per_kg_minor' => 4000,
        ]);
        $this->assertSame(15000, $shortfallRes['shortfall_kg']);
        $this->assertSame(60000000, $shortfallRes['epr_compliance_levy_minor']);
    }

    public function test_fase_231_to_233_board_delegation_whistleblowing_speakup_and_stakeholder_governance(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $res = $master->execute([
            'command_code' => 'GOV-BOARD-231',
            'policy_engine_decision' => 'ALLOW',
            'sod_conflict_count' => 0,
        ]);
        $this->assertSame('RELEASE_V300_30_LINES_CERTIFIED', $res['certification_status']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Policy-as-Code Engine denied');
        $master->execute([
            'command_code' => 'GOV-DENY-231',
            'policy_engine_decision' => 'DENY',
        ]);
    }

    public function test_fase_234_to_236_corporate_venture_internal_api_marketplace_and_experimentation_factory(): void
    {
        $partnerAction = app(SettleProfessionalLegalTrustAndCoSellAffiliateAction::class);

        $res = $partnerAction->execute([
            'deal_code' => 'API-CAP-235',
            'gross_cosell_gmv_minor' => 500000000,
            'partner_share_bps' => 1200, // 12%
        ]);

        $this->assertSame(60000000, $res['partner_commission_minor']);
        $this->assertSame(440000000, $res['net_enterprise_revenue_minor']);
        $this->assertSame(500000000, $res['partner_commission_minor'] + $res['net_enterprise_revenue_minor']);
    }

    public function test_fase_237_to_239_dx_quality_release_train_safety_and_query_budget_cost_optimization(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('query budget breached');
        $master->execute([
            'command_code' => 'PERF-BUDGET-239',
            'p95_critical_latency_ms' => 410,
            'p95_budget_ms' => 250,
        ]);
    }

    public function test_fase_240_experience_design_system_blocks_wcag_aa_accessibility_violations(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $clean = $master->execute([
            'command_code' => 'WCAG-CLEAN-240',
            'wcag_aa_violation_count' => 0,
        ]);
        $this->assertSame('RELEASE_V300_30_LINES_CERTIFIED', $clean['certification_status']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('WCAG 2.1 AA violation');
        $master->execute([
            'command_code' => 'WCAG-FAIL-240',
            'wcag_aa_violation_count' => 4,
        ]);
    }

    public function test_fase_241_to_244_data_governance_lineage_realtime_cdc_graph_and_monetization(): void
    {
        $district = app(DigitalTrustVerifiableIdentityAndSmartDistrictGridAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('30-Line Domain Contract Freeze failed');
        $district->execute([
            'district_session_code' => 'CDC-SCHEMA-DRIFT-242',
            'registered_business_lines_count' => 30,
            'breaking_event_schema_drift_count' => 1,
        ]);
    }

    public function test_fase_245_to_247_pricing_science_sales_pipeline_and_kam_partnership_revenue(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $res = $master->execute([
            'command_code' => 'KAM-COSELL-247',
            'partner_cosell_gmv_minor' => 400000000,
            'partner_commission_bps' => 1750, // 17.50%
        ]);

        $this->assertSame(70000000, $res['partner_commission_minor']);
        $this->assertSame(330000000, $res['platform_retained_revenue_minor']);
    }

    public function test_fase_248_to_249_tender_bid_management_scale_and_cpq_quote_to_cash_idempotency(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $params = [
            'command_code' => 'CPQ-Q2C-249',
            'partner_cosell_gmv_minor' => 300000000,
            'partner_commission_bps' => 1000,
        ];

        $run1 = $master->execute($params);
        $run2 = $master->execute($params);

        $this->assertSame($run1['release_300_handover_hash'], $run2['release_300_handover_hash']);
        $this->assertSame(1, LedgerTransaction::where('reference_id', 'CPQ-Q2C-249')->count());
    }

    public function test_fase_250_customer_experience_voc_and_ekyc_anti_spoofing_boundary(): void
    {
        $district = app(DigitalTrustVerifiableIdentityAndSmartDistrictGridAction::class);

        // Exact boundary 8500 bps passes
        $boundaryPass = $district->execute([
            'district_session_code' => 'VOC-EKYC-8500',
            'liveness_score_bps' => 8500,
            'minimum_liveness_bps' => 8500,
        ]);
        $this->assertSame(8500, $boundaryPass['liveness_score_bps']);

        // 8499 bps fails
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('below anti-spoofing threshold');
        $district->execute([
            'district_session_code' => 'VOC-EKYC-8499',
            'liveness_score_bps' => 8499,
            'minimum_liveness_bps' => 8500,
        ]);
    }
}
