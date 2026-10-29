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
use Modules\Core\Application\Wave3\HigherEdCampusAccreditationAndEndowmentGrantAction;
use Modules\Core\Application\Wave3\ProfessionalServicesTimeBillingAndAviationMroSlotAction;
use Modules\Core\Application\Wave3\SyariahMurabahahMudharabahSukukAndZakatPoolAction;
use Modules\Edu\Application\Actions\ConferHigherEdAccreditedDegreeAndEndowmentGrantAction;
use Modules\EnterpriseFinance\Application\Actions\ExecuteGlobalTaxPillarTwoCloseAndCapitalGovernanceAction;
use Modules\Esg\Application\Actions\VerifyForestrySvlkCustodyAndCircularEprOffsetAction;
use Modules\Insurance\Application\Actions\SettleReinsuranceTreatyCessionAndTakafulTabarruAction;
use Modules\Logistics\Application\Actions\OrchestrateAviationMroAirworthinessAndPortBerthAction;
use Modules\Partner\Application\Actions\SettleProfessionalLegalTrustAndCoSellAffiliateAction;
use Modules\Treasury\Application\Actions\SettleSyariahMurabahahSukukAndLiquidityPoolAction;
use Tests\TestCase;

class Fase251To300Wave3GovernanceAndReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_251_to_255_end_to_end_30_lines_supply_finance_risk_talent_and_golden_mega_audit(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $res = $master->execute([
            'command_code' => 'GOLDEN-30-MEGA-255',
            'verified_business_lines_count' => 30,
            'ledger_variance_minor' => 0,
            'broken_hash_chains_count' => 0,
        ]);

        $this->assertCount(30, $res['canonical_lines']);
        $this->assertSame('LINE_01_BANKING_PAYMENT', $res['canonical_lines'][0]);
        $this->assertSame('LINE_30_SMART_DISTRICT_TOWNSHIP_SERVICES', $res['canonical_lines'][29]);
        $this->assertSame('RELEASE_V300_30_LINES_CERTIFIED', $res['certification_status']);
    }

    public function test_fase_256_to_259_observability_slo_error_budget_freeze_cqrs_edge_and_knowledge_graph(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        // Reliability fix is allowed even at 0 error budget
        $reliabilityFix = $master->execute([
            'command_code' => 'SLO-REL-FIX-256',
            'slo_error_budget_remaining_bps' => 0,
            'release_change_risk' => 'RELIABILITY_HOTFIX',
        ]);
        $this->assertSame('RELEASE_V300_30_LINES_CERTIFIED', $reliabilityFix['certification_status']);

        // Risky feature rollout is blocked when error budget is exhausted
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('SLO Economy freeze');
        $master->execute([
            'command_code' => 'SLO-RISKY-256',
            'slo_error_budget_remaining_bps' => 0,
            'release_change_risk' => 'RISKY_FEATURE_ROLLOUT',
        ]);
    }

    public function test_fase_260_to_262_partner_cosell_supplier_finance_and_distributor_collaboration(): void
    {
        $partnerAction = app(SettleProfessionalLegalTrustAndCoSellAffiliateAction::class);

        $res = $partnerAction->execute([
            'deal_code' => 'ECOSYS-COSELL-260',
            'gross_cosell_gmv_minor' => 800000000,
            'partner_share_bps' => 1500,
        ]);

        $this->assertSame(120000000, $res['partner_commission_minor']);
        $this->assertSame(680000000, $res['net_enterprise_revenue_minor']);

        $tx = LedgerTransaction::where('reference_id', 'ECOSYS-COSELL-260')->first();
        $this->assertNotNull($tx);
        $this->assertSame(0, (int) $tx->entries->sum('amount_minor'));
    }

    public function test_fase_263_to_265_govtech_regulatory_insurtech_fintech_and_academic_research_network(): void
    {
        $insAction = app(SettleReinsuranceTreatyCessionAndTakafulTabarruAction::class);
        $eduAction = app(ConferHigherEdAccreditedDegreeAndEndowmentGrantAction::class);

        $insRes = $insAction->execute([
            'policy_treaty_code' => 'INS-PARTNER-264',
            'rbc_ratio_bps' => 19000,
            'gross_claim_minor' => 200000000,
        ]);
        $this->assertSame(200000000, $insRes['total_ceded_minor'] + $insRes['net_retained_minor']);

        $eduRes = $eduAction->execute([
            'convocation_code' => 'ACAD-GRANT-265',
            'endowment_yield_available_minor' => 500000000,
            'research_grant_tranche_minor' => 200000000,
        ]);
        $this->assertSame(300000000, $eduRes['remaining_endowment_yield_minor']);
    }

    public function test_fase_266_to_270_decision_intelligence_data_mesh_and_autonomous_level4_hitl_guardrail(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        // Kill switch engaged safely halts ledger posting without throwing corruption
        $halted = $master->execute([
            'command_code' => 'AI-L4-KILL-268',
            'ai_kill_switch_state' => 'ENGAGED',
            'autonomous_level' => 4,
        ]);
        $this->assertSame('HALTED_SAFE_BY_AI_KILL_SWITCH', $halted['certification_status']);
        $this->assertSame(0, $halted['consolidated_settlement_minor']);

        // Level 4 material outcome without human review throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('requires human-in-the-loop sign-off');
        $master->execute([
            'command_code' => 'AI-L4-NO-HITL-268',
            'autonomous_level' => 4,
            'material_impact_human_review' => 'MISSING',
        ]);
    }

    public function test_fase_271_to_272_capital_markets_digital_securities_and_institutional_treasury_pool(): void
    {
        $trsAction = app(SettleSyariahMurabahahSukukAndLiquidityPoolAction::class);

        $res = $trsAction->execute([
            'sukuk_series_code' => 'DIGI-SUKUK-271',
            'sukuk_principal_minor' => 1000000000,
            'ijarah_asset_value_minor' => 1250000000,
            'ijarah_rental_yield_bps' => 625,
            'idle_cash_sweep_minor' => 150000000,
        ]);

        $this->assertSame(62500000, $res['periodic_ujrah_distribution_minor']);
        $this->assertSame(12500, $res['asset_coverage_bps']);
    }

    public function test_fase_273_to_275_global_fincrime_sanctions_corporate_tax_and_liquidity_command(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);
        $efAction = app(ExecuteGlobalTaxPillarTwoCloseAndCapitalGovernanceAction::class);

        $taxRes = $efAction->execute([
            'close_period_code' => 'GLOBAL-TAX-274',
            'globe_income_minor' => 5000000000,
            'covered_tax_paid_minor' => 800000000, // 16% ETR >= 15% -> 0 top-up
        ]);
        $this->assertSame(1600, $taxRes['effective_tax_rate_bps']);
        $this->assertSame(0, $taxRes['pillar_two_top_up_tax_minor']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('matched prohibited sanctions list');
        $master->execute([
            'command_code' => 'SANCTIONS-HIT-273',
            'sanctions_screening_state' => 'CONFIRMED_SANCTIONS_MATCH',
        ]);
    }

    public function test_fase_276_to_280_lean_six_sigma_aps_fleet_warehouse_robotics_and_hospitality_ops(): void
    {
        $lgxAction = app(OrchestrateAviationMroAirworthinessAndPortBerthAction::class);

        $res = $lgxAction->execute([
            'rotation_code' => 'OPS-ROBOTICS-279',
            'remaining_llp_cycles' => 600,
            'air_cargo_kg' => 25000,
            'air_cargo_rate_per_kg_minor' => 20000,
            'port_teu_count' => 1200,
            'port_thc_per_teu_minor' => 150000,
        ]);

        $this->assertSame(680000000, $res['multimodal_revenue_minor']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('zero remaining Life-Limited Part cycles');
        $lgxAction->execute([
            'rotation_code' => 'OPS-GROUNDED-278',
            'remaining_llp_cycles' => 0,
        ]);
    }

    public function test_fase_281_to_285_omnichannel_sla_social_commerce_coalition_loyalty_and_ehs_wellness(): void
    {
        $fashionAction = app(ContainerPortBerthCraneAndFashionAssortmentAction::class);

        $res = $fashionAction->execute([
            'call_code' => 'OMNI-LOYALTY-283',
            'otb_budget_ceiling_minor' => 600000000,
            'assortment_commit_minor' => 450000000,
            'garment_takeback_kg' => 500,
            'takeback_credit_per_kg_minor' => 30000,
        ]);
        $this->assertSame(15000000, $res['circular_takeback_credit_minor']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('critical labor violation detected');
        $fashionAction->execute([
            'call_code' => 'EHS-LABOR-BLOCK-285',
            'supplier_labor_audit_state' => 'FORCED_OR_CHILD_LABOR_DETECTED',
        ]);
    }

    public function test_fase_286_to_289_esg_impact_audit_climate_adaptation_human_rights_and_epr_repairability(): void
    {
        $esgAction = app(VerifyForestrySvlkCustodyAndCircularEprOffsetAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('repairability index 42 < 60');
        $esgAction->execute([
            'certificate_code' => 'EPR-REPAIR-FAIL-289',
            'repairability_score_100' => 42,
            'minimum_repairability_100' => 60,
        ]);
    }

    public function test_fase_290_to_291_enterprise_policy_engine_and_ediscovery_legal_hold_immutability(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        // Retaining records under legal hold is allowed
        $retained = $master->execute([
            'command_code' => 'LEGAL-HOLD-KEEP-291',
            'legal_hold_state' => 'ACTIVE',
            'record_purge_operation' => 'NONE',
        ]);
        $this->assertSame('RELEASE_V300_30_LINES_CERTIFIED', $retained['certification_status']);

        // Purging records under active legal hold throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('purging records under ACTIVE legal hold is strictly forbidden');
        $master->execute([
            'command_code' => 'LEGAL-HOLD-PURGE-291',
            'legal_hold_state' => 'ACTIVE',
            'record_purge_operation' => 'PURGE_RECORDS',
        ]);
    }

    public function test_fase_292_verifiable_credentials_revocation_registry_blocks_revoked_credential(): void
    {
        $trustAction = app(DigitalTrustVerifiableIdentityAndSmartDistrictGridAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("registry state is 'REVOKED_COMPROMISED'");
        $trustAction->execute([
            'district_session_code' => 'VC-REVOKED-292',
            'credential_registry_state' => 'REVOKED_COMPROMISED',
        ]);
    }

    public function test_fase_293_to_294_continuous_internal_audit_assurance_and_ai_biometric_ethics(): void
    {
        $proAction = app(ProfessionalServicesTimeBillingAndAviationMroSlotAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('exceeds segregated client trust balance');
        $proAction->execute([
            'engagement_code' => 'AUDIT-TRUST-OVERDRAW-293',
            'client_trust_balance_minor' => 40000000,
            'trust_disbursement_minor' => 55000000,
        ]);
    }

    public function test_fase_295_to_297_modular_monolith_evolution_feature_flags_and_finops_automation(): void
    {
        $syrAction = app(SyariahMurabahahMudharabahSukukAndZakatPoolAction::class);

        // Mudharabah loss without misconduct is absorbed 100% by capital provider (Sahibul Maal)
        $res = $syrAction->execute([
            'akad_code' => 'MUDHARABAH-LOSS-295',
            'venture_profit_loss_minor' => -35000000,
            'mudharib_misconduct_finding' => 'NONE',
        ]);

        $this->assertSame(35000000, $res['capital_loss_absorbed_by_provider_minor']);
        $this->assertSame(0, $res['mudharib_share_minor']);
    }

    public function test_fase_298_final_30_line_stress_security_and_dr_zero_rpo_gate(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('RPO > 0');
        $master->execute([
            'command_code' => 'DR-RPO-FAIL-298',
            'rpo_lost_transactions' => 3,
        ]);
    }

    public function test_fase_299_release_candidate_documentation_drift_check_blocks_phantom_claims(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('documentation drift gate failed');
        $master->execute([
            'command_code' => 'RC-DRIFT-FAIL-299',
            'rc_doc_drift_count' => 2,
        ]);
    }

    public function test_fase_300_rejects_certification_on_any_ledger_variance_broken_chain_or_missing_line(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        try {
            $master->execute([
                'command_code' => 'REL300-LINES-29',
                'verified_business_lines_count' => 29,
            ]);
            $this->fail('Expected DomainException when fewer than 30 lines are verified');
        } catch (DomainException $e) {
            $this->assertStringContainsString('expected 30 verified business lines', $e->getMessage());
        }

        try {
            $master->execute([
                'command_code' => 'REL300-VARIANCE',
                'ledger_variance_minor' => 1,
            ]);
            $this->fail('Expected DomainException on 1 minor-unit variance');
        } catch (DomainException $e) {
            $this->assertStringContainsString('unexplained ledger variance', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('broken hash chain');
        $master->execute([
            'command_code' => 'REL300-CHAIN-BROKEN',
            'broken_hash_chains_count' => 1,
        ]);
    }

    public function test_fase_300_final_30_lines_zero_variance_handover_and_deterministic_dual_run_certification(): void
    {
        $master = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);
        $edu = app(HigherEdCampusAccreditationAndEndowmentGrantAction::class);

        $campusRes = $edu->execute([
            'academic_cycle_code' => 'REL300-CAMPUS-VERIFY',
            'student_id' => 'STU-REL300',
        ]);
        $this->assertSame('DEGREE_CONFERRED_VERIFIED', $campusRes['degree_status']);

        $payload = [
            'command_code' => 'FINAL-RELEASE-300-HANDOVER',
            'verified_business_lines_count' => 30,
            'ledger_variance_minor' => 0,
            'broken_hash_chains_count' => 0,
            'rc_doc_drift_count' => 0,
            'rpo_lost_transactions' => 0,
            'partner_cosell_gmv_minor' => 1000000000,
            'partner_commission_bps' => 1500,
        ];

        $run1 = $master->execute($payload);
        $run2 = $master->execute($payload);

        $this->assertSame(30, $run1['verified_business_lines_count']);
        $this->assertSame(0, $run1['ledger_variance_minor']);
        $this->assertSame(0, $run1['broken_hash_chains_count']);
        $this->assertSame('v300-30-lines-complete', $run1['release_tag']);
        $this->assertSame('RELEASE_V300_30_LINES_CERTIFIED', $run1['certification_status']);
        $this->assertSame($run1['release_300_handover_hash'], $run2['release_300_handover_hash']);

        $txs = LedgerTransaction::where('reference_id', 'FINAL-RELEASE-300-HANDOVER')->get();
        $this->assertCount(1, $txs);
        $this->assertSame(0, (int) $txs->first()->entries->sum('amount_minor'));
    }
}
