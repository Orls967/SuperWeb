<?php

declare(strict_types=1);

namespace Tests\Feature\Wave3;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agri\Application\Actions\CertifyHalalFoodMassBalanceAndAquacultureCatchAction;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Application\Wave3\ContainerPortBerthCraneAndFashionAssortmentAction;
use Modules\Core\Application\Wave3\DigitalTrustVerifiableIdentityAndSmartDistrictGridAction;
use Modules\Core\Application\Wave3\Enterprise30LinesMasterCommandAndRelease300HandoverAction;
use Modules\Core\Application\Wave3\FoodProcessingHalalBatchAndMarineColdVesselAction;
use Modules\Core\Application\Wave3\ForestrySvlkChainOfCustodyAndCircularEprCreditAction;
use Modules\Core\Application\Wave3\GlobalMultiCountryHqAndMobilityOrchestratorAction;
use Modules\Core\Application\Wave3\HigherEdCampusAccreditationAndEndowmentGrantAction;
use Modules\Core\Application\Wave3\ProfessionalServicesTimeBillingAndAviationMroSlotAction;
use Modules\Core\Application\Wave3\ReinsuranceTreatyCatastropheAndTakafulSettlementAction;
use Modules\Core\Application\Wave3\SyariahMurabahahMudharabahSukukAndZakatPoolAction;
use Modules\Edu\Application\Actions\ConferHigherEdAccreditedDegreeAndEndowmentGrantAction;
use Modules\Esg\Application\Actions\VerifyForestrySvlkCustodyAndCircularEprOffsetAction;
use Modules\Insurance\Application\Actions\SettleReinsuranceTreatyCessionAndTakafulTabarruAction;
use Modules\Logistics\Application\Actions\OrchestrateAviationMroAirworthinessAndPortBerthAction;
use Modules\Partner\Application\Actions\SettleProfessionalLegalTrustAndCoSellAffiliateAction;
use Modules\Treasury\Application\Actions\SettleSyariahMurabahahSukukAndLiquidityPoolAction;
use Tests\TestCase;

class Fase151To200Wave3Lines18To30Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_151_to_155_global_hq_shadow_payroll_tax_equalization_and_bcp_failover(): void
    {
        $action = app(GlobalMultiCountryHqAndMobilityOrchestratorAction::class);

        $res = $action->execute([
            'operation_code' => 'GHQ-SG-2026-01',
            'region_code' => 'APAC',
            'country_code' => 'SG',
            'target_stage' => 'LIVE',
            'base_salary_minor' => 200000000,
            'cola_allowance_minor' => 30000000,
            'home_hypo_tax_minor' => 35000000,
            'host_actual_tax_minor' => 50000000,
            'primary_corridor_state' => 'PORT_STRIKE_DISRUPTED',
        ]);

        $this->assertSame(15000000, $res['tax_equalization_topup_minor']);
        $this->assertSame(245000000, $res['total_mobility_cost_minor']);
        $this->assertSame('FAILOVER_SECONDARY_CORRIDOR_ACTIVE', $res['bcp_mode']);
        $this->assertSame(100, $res['total_sourcing_allocation_pct']);
        $this->assertSame(0, $res['primary_allocation_pct']);
        $this->assertSame(100, $res['secondary_allocation_pct']);

        $tx = LedgerTransaction::where('reference_id', 'GHQ-SG-2026-01')->first();
        $this->assertNotNull($tx);
        $this->assertSame(0, (int) $tx->entries->sum('amount_minor'));
    }

    public function test_fase_151_to_155_ethical_workforce_blocks_worker_recruitment_fee_and_incomplete_market_entry(): void
    {
        $action = app(GlobalMultiCountryHqAndMobilityOrchestratorAction::class);

        try {
            $action->execute([
                'operation_code' => 'GHQ-ETHICS-BLOCK',
                'worker_recruitment_fee_minor' => 5000000,
            ]);
            $this->fail('Expected DomainException for unethical migrant recruitment fee');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Ethical sourcing violation', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("pillar 'data_residency' is 'PENDING'");
        $action->execute([
            'operation_code' => 'GHQ-ENTRY-BLOCK',
            'target_stage' => 'LIVE',
            'data_residency_status' => 'PENDING',
        ]);
    }

    public function test_fase_156_to_160_reinsurance_quota_xol_waterfall_cat_trigger_and_takaful_surplus(): void
    {
        $orchestrator = app(ReinsuranceTreatyCatastropheAndTakafulSettlementAction::class);
        $ownerAction = app(SettleReinsuranceTreatyCessionAndTakafulTabarruAction::class);

        // Gross claim 600M: 30% Quota-Share = 180M ceded -> 420M net -> XoL over 200M cap = 220M ceded (within 250M limit) -> Retained = 200M
        $res = $orchestrator->execute([
            'settlement_code' => 'REINS-CAT-156',
            'rbc_ratio_bps' => 17500,
            'gross_claim_minor' => 600000000,
            'quota_share_cession_bps' => 3000,
            'xol_retention_cap_minor' => 200000000,
            'xol_layer_limit_minor' => 250000000,
            'tabarru_contribution_minor' => 100000000,
            'wakalah_fee_bps' => 1500,
            'tabarru_claims_paid_minor' => 65000000,
            'cat_richter_scaled_10' => 68,
            'cat_trigger_threshold_scaled_10' => 65,
        ]);

        $this->assertSame(180000000, $res['quota_ceded_minor']);
        $this->assertSame(220000000, $res['xol_ceded_minor']);
        $this->assertSame(200000000, $res['retained_claim_minor']);
        $this->assertSame(600000000, $res['retained_claim_minor'] + $res['total_reinsurance_recoverable_minor']);
        $this->assertSame(14000000, $res['participant_surplus_share_minor']); // 70% of (85M - 65M = 20M)
        $this->assertSame(6000000, $res['operator_surplus_share_minor']);
        $this->assertSame('CAT_PARAMETRIC_TRIGGERED', $res['cat_parametric_state']);

        $ownerRes = $ownerAction->execute([
            'policy_treaty_code' => 'INS-OWNER-160',
            'gross_claim_minor' => 400000000,
            'quota_share_bps' => 2500,
            'xol_attachment_minor' => 200000000,
            'tabarru_pool_minor' => 70000000,
            'tabarru_claims_minor' => 95000000,
        ]);
        $this->assertSame(25000000, $ownerRes['qardh_hasan_loan_minor']);
        $this->assertSame(400000000, $ownerRes['total_ceded_minor'] + $ownerRes['net_retained_minor']);
    }

    public function test_fase_156_to_160_reinsurance_rejects_underwriting_below_120_percent_rbc_solvency(): void
    {
        $action = app(ReinsuranceTreatyCatastropheAndTakafulSettlementAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('below regulatory minimum 12000 bps');
        $action->execute([
            'settlement_code' => 'REINS-LOW-RBC',
            'rbc_ratio_bps' => 11500,
        ]);
    }

    public function test_fase_161_to_165_syariah_murabahah_mudharabah_sukuk_charity_routing_and_zakat_pool(): void
    {
        $orchestrator = app(SyariahMurabahahMudharabahSukukAndZakatPoolAction::class);
        $treasuryAction = app(SettleSyariahMurabahahSukukAndLiquidityPoolAction::class);

        $res = $orchestrator->execute([
            'akad_code' => 'SYR-MUR-161',
            'underlying_sector' => 'HALAL_LOGISTICS',
            'riba_interest_bps' => 0,
            'murabahah_asset_cost_minor' => 300000000,
            'murabahah_margin_minor' => 45000000,
            'late_tazir_penalty_minor' => 2500000,
            'sahibul_maal_nisbah_bps' => 6500,
            'mudharib_nisbah_bps' => 3500,
            'venture_profit_loss_minor' => 100000000,
            'sukuk_issuance_minor' => 500000000,
            'underlying_ijarah_asset_minor' => 650000000,
            'zakatable_net_assets_minor' => 2000000000,
            'nisab_threshold_minor' => 120000000,
        ]);

        $this->assertSame(345000000, $res['murabahah_selling_price_minor']);
        $this->assertSame(45000000, $res['recognized_halal_margin_minor']);
        $this->assertSame(2500000, $res['charity_pool_allocation_minor']);
        $this->assertSame(65000000, $res['sahibul_maal_share_minor']);
        $this->assertSame(35000000, $res['mudharib_share_minor']);
        $this->assertSame(50000000, $res['zakat_obligation_minor']); // 2.5% of 2B

        $sukukRes = $treasuryAction->execute([
            'sukuk_series_code' => 'SUKUK-IJARAH-162',
            'sukuk_principal_minor' => 500000000,
            'ijarah_asset_value_minor' => 600000000,
            'ijarah_rental_yield_bps' => 700,
        ]);
        $this->assertSame(35000000, $sukukRes['periodic_ujrah_distribution_minor']);
        $this->assertSame(12000, $sukukRes['asset_coverage_bps']);
    }

    public function test_fase_161_to_165_syariah_blocks_riba_non_halal_sector_unbacked_sukuk_and_wakaf_corpus_erosion(): void
    {
        $action = app(SyariahMurabahahMudharabahSukukAndZakatPoolAction::class);

        try {
            $action->execute(['akad_code' => 'SYR-RIBA', 'riba_interest_bps' => 500]);
            $this->fail('Expected DomainException for riba interest');
        } catch (DomainException $e) {
            $this->assertStringContainsString('riba interest', $e->getMessage());
        }

        try {
            $action->execute(['akad_code' => 'SYR-HARAM', 'underlying_sector' => 'GAMBLING']);
            $this->fail('Expected DomainException for non-halal sector');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Shariah screening rejected', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Wakaf perpetuity violation');
        $action->execute([
            'akad_code' => 'SYR-WAKAF-FAIL',
            'wakaf_principal_drawdown_minor' => 1000000,
        ]);
    }

    public function test_fase_166_to_167_higher_ed_campus_sks_accreditation_endowment_and_diploma_hash(): void
    {
        $orchestrator = app(HigherEdCampusAccreditationAndEndowmentGrantAction::class);
        $eduOwner = app(ConferHigherEdAccreditedDegreeAndEndowmentGrantAction::class);

        $res = $orchestrator->execute([
            'academic_cycle_code' => 'CAMPUS-166-ODD',
            'student_id' => 'STU-2026-88',
            'completed_total_sks' => 148,
            'gpa_scaled_100' => 382,
            'annual_endowment_yield_minor' => 800000000,
            'requested_grant_drawdown_minor' => 500000000,
            'gross_tuition_minor' => 30000000,
            'scholarship_aid_minor' => 10000000,
        ]);

        $this->assertSame('UNGGUL_A', $res['accreditation_tier']);
        $this->assertSame(300000000, $res['remaining_spendable_yield_minor']);
        $this->assertSame(20000000, $res['net_tuition_billed_minor']);
        $this->assertSame('DEGREE_CONFERRED_VERIFIED', $res['degree_status']);

        $ownerRes = $eduOwner->execute([
            'convocation_code' => 'EDU-CONV-167',
            'earned_sks' => 146,
            'gpa_scaled_100' => 375,
        ]);
        $this->assertSame('CUM_LAUDE', $ownerRes['honors_classification']);
    }

    public function test_fase_166_to_167_higher_ed_blocks_endowment_principal_invasion_and_insufficient_sks(): void
    {
        $action = app(HigherEdCampusAccreditationAndEndowmentGrantAction::class);

        try {
            $action->execute([
                'academic_cycle_code' => 'CAMPUS-ENDOW-FAIL',
                'annual_endowment_yield_minor' => 200000000,
                'requested_grant_drawdown_minor' => 250000000,
            ]);
            $this->fail('Expected DomainException on endowment principal invasion');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Endowment perpetuity violation', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('below required 144 SKS');
        $action->execute([
            'academic_cycle_code' => 'CAMPUS-SKS-FAIL',
            'completed_total_sks' => 120,
            'degree_conferral_action' => 'CONFER_DEGREE',
        ]);
    }

    public function test_fase_168_to_171_food_processing_halal_mass_balance_and_marine_tac_cold_chain(): void
    {
        $orchestrator = app(FoodProcessingHalalBatchAndMarineColdVesselAction::class);
        $agriOwner = app(CertifyHalalFoodMassBalanceAndAquacultureCatchAction::class);

        $res = $orchestrator->execute([
            'batch_code' => 'HALAL-MAR-168',
            'raw_agri_input_kg' => 10000,
            'finished_food_kg' => 8000,
            'byproduct_feed_kg' => 1400,
            'process_loss_kg' => 600,
            'vessel_catch_kg' => 4000,
            'remaining_tac_quota_kg' => 7500,
            'max_recorded_temp_centi_celsius' => -2100,
            'export_price_per_kg_minor' => 50000,
        ]);

        $this->assertSame(8000, $res['yield_efficiency_bps']);
        $this->assertSame(3500, $res['remaining_tac_after_catch_kg']);
        $this->assertSame('COLD_CHAIN_INTACT', $res['cold_chain_status']);
        $this->assertSame(600000000, $res['total_batch_revenue_minor']);

        $agriRes = $agriOwner->execute([
            'lot_code' => 'AGRI-LOT-171',
            'raw_input_kg' => 4000,
            'net_packaged_kg' => 3200,
            'byproduct_kg' => 500,
            'aquaculture_harvest_kg' => 2000,
            'feed_consumed_kg' => 2600,
        ]);
        $this->assertSame(300, $agriRes['mass_loss_kg']);
        $this->assertSame(130, $agriRes['fcr_scaled_100']);
    }

    public function test_fase_168_to_171_blocks_iuu_dark_vessel_mass_imbalance_and_cold_excursion_export(): void
    {
        $action = app(FoodProcessingHalalBatchAndMarineColdVesselAction::class);

        try {
            $action->execute([
                'batch_code' => 'MAR-IUU-DARK',
                'vms_transponder_state' => 'OFFLINE_DARK_VESSEL',
            ]);
            $this->fail('Expected DomainException for dark VMS vessel');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Marine anti-IUU gate blocked', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('temperature excursion');
        $action->execute([
            'batch_code' => 'MAR-TEMP-BREACH',
            'max_recorded_temp_centi_celsius' => -1100,
            'max_allowed_temp_centi_celsius' => -1800,
            'export_release_action' => 'RELEASE_FOR_EXPORT',
        ]);
    }

    public function test_fase_172_to_175_forestry_svlk_aac_nature_finance_and_circular_epr_credits(): void
    {
        $orchestrator = app(ForestrySvlkChainOfCustodyAndCircularEprCreditAction::class);
        $esgOwner = app(VerifyForestrySvlkCustodyAndCircularEprOffsetAction::class);

        $res = $orchestrator->execute([
            'custody_code' => 'SVLK-EPR-172',
            'harvested_timber_m3' => 1000,
            'annual_allowable_cut_m3' => 1500,
            'sawn_timber_output_m3' => 750,
            'restored_hectares' => 300,
            'nature_credit_rate_per_ha_minor' => 500000,
            'packaging_placed_on_market_kg' => 40000,
            'mandatory_recovery_target_bps' => 5000,
            'verified_recycled_kg' => 28000,
            'epr_credit_price_per_kg_minor' => 4000,
        ]);

        $this->assertSame(500, $res['remaining_aac_m3']);
        $this->assertSame(7500, $res['sawmill_recovery_bps']);
        $this->assertSame(150000000, $res['nature_finance_credit_minor']);
        $this->assertSame(8000, $res['surplus_recycled_kg']);
        $this->assertSame(32000000, $res['circular_credit_value_minor']);

        $esgRes = $esgOwner->execute([
            'certificate_code' => 'ESG-SVLK-174',
            'felled_logs_m3' => 800,
            'aac_ceiling_m3' => 1000,
            'repairability_score_100' => 85,
        ]);
        $this->assertSame(200, $esgRes['remaining_aac_m3']);
        $this->assertSame(85, $esgRes['repairability_score_100']);
    }

    public function test_fase_172_to_175_blocks_peatland_encroachment_and_duplicate_epr_lot_retirement(): void
    {
        $action = app(ForestrySvlkChainOfCustodyAndCircularEprCreditAction::class);

        try {
            $action->execute([
                'custody_code' => 'SVLK-PEAT-FAIL',
                'protected_peatland_overlap_m2' => 450,
            ]);
            $this->fail('Expected DomainException on peatland overlap');
        } catch (DomainException $e) {
            $this->assertStringContainsString('protected peatland', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('double-counting blocked');
        $action->execute([
            'custody_code' => 'EPR-DUP-LOT',
            'recycled_lot_id' => 'EPR-LOT-99',
            'already_retired_lot_ids' => ['EPR-LOT-99'],
        ]);
    }

    public function test_fase_176_to_179_professional_legal_trust_wip_and_aviation_mro_airworthiness(): void
    {
        $orchestrator = app(ProfessionalServicesTimeBillingAndAviationMroSlotAction::class);
        $partnerOwner = app(SettleProfessionalLegalTrustAndCoSellAffiliateAction::class);

        $res = $orchestrator->execute([
            'engagement_code' => 'LEGAL-AVI-176',
            'client_party_id' => 'CORP-CLIENT-1',
            'adverse_party_id' => 'CORP-ADVERSE-2',
            'client_trust_balance_minor' => 150000000,
            'trust_disbursement_minor' => 50000000,
            'billable_hours' => 100,
            'hourly_rate_minor' => 3000000,
            'wip_write_down_minor' => 20000000,
            'aircraft_tail_number' => 'PK-W3-777',
            'accumulated_flight_cycles' => 12000,
            'llp_max_cycle_limit' => 15000,
        ]);

        $this->assertSame(280000000, $res['realized_consulting_fee_minor']);
        $this->assertSame(100000000, $res['remaining_client_trust_minor']);
        $this->assertSame(3000, $res['remaining_llp_cycles']);
        $this->assertSame('AIRWORTHY_RELEASED_CRS', $res['mro_airworthiness_status']);

        $ptnRes = $partnerOwner->execute([
            'deal_code' => 'PTN-LEGAL-176',
            'gross_cosell_gmv_minor' => 300000000,
            'partner_share_bps' => 2000,
        ]);
        $this->assertSame(60000000, $ptnRes['partner_commission_minor']);
        $this->assertSame(240000000, $ptnRes['net_enterprise_revenue_minor']);
    }

    public function test_fase_176_to_179_blocks_legal_conflict_of_interest_and_expired_llp_aircraft_dispatch(): void
    {
        $action = app(ProfessionalServicesTimeBillingAndAviationMroSlotAction::class);

        try {
            $action->execute([
                'engagement_code' => 'LEGAL-CONFLICT',
                'adverse_party_id' => 'PTY-EXISTING-CLIENT',
                'conflicted_active_parties' => ['PTY-EXISTING-CLIENT'],
            ]);
            $this->fail('Expected DomainException on legal conflict of interest');
        } catch (DomainException $e) {
            $this->assertStringContainsString('conflict-of-interest blocked', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Aviation safety gate grounded aircraft');
        $action->execute([
            'engagement_code' => 'AVI-LLP-EXPIRED',
            'accumulated_flight_cycles' => 15000,
            'llp_max_cycle_limit' => 15000,
            'flight_dispatch_action' => 'DISPATCH_FLIGHT',
        ]);
    }

    public function test_fase_180_to_183_container_port_laytime_cii_rating_and_fashion_otb_circular_takeback(): void
    {
        $orchestrator = app(ContainerPortBerthCraneAndFashionAssortmentAction::class);
        $lgxOwner = app(OrchestrateAviationMroAirworthinessAndPortBerthAction::class);

        $res = $orchestrator->execute([
            'call_code' => 'PORT-FSH-180',
            'vessel_draft_cm' => 1400,
            'berth_max_draft_cm' => 1550,
            'teu_handled_count' => 2000,
            'thc_per_teu_minor' => 150000,
            'allowed_laytime_hours' => 30,
            'actual_port_stay_hours' => 36,
            'demurrage_hourly_rate_minor' => 4000000,
            'cii_grams_co2_per_dwt_nm' => 33,
            'otb_budget_ceiling_minor' => 500000000,
            'assortment_commit_minor' => 350000000,
            'garment_takeback_kg' => 200,
            'takeback_credit_per_kg_minor' => 25000,
        ]);

        $this->assertSame(300000000, $res['terminal_handling_revenue_minor']);
        $this->assertSame(24000000, $res['demurrage_minor']);
        $this->assertSame('A', $res['imo_cii_rating']);
        $this->assertSame(150000000, $res['remaining_otb_budget_minor']);
        $this->assertSame(5000000, $res['circular_takeback_credit_minor']);

        $lgxRes = $lgxOwner->execute([
            'rotation_code' => 'LGX-PORT-180',
            'remaining_llp_cycles' => 300,
            'port_teu_count' => 1000,
        ]);
        $this->assertGreaterThan(0, $lgxRes['multimodal_revenue_minor']);
    }

    public function test_fase_180_to_183_blocks_missing_solas_vgm_containers_and_fashion_otb_overrun(): void
    {
        $action = app(ContainerPortBerthCraneAndFashionAssortmentAction::class);

        try {
            $action->execute([
                'call_code' => 'PORT-NO-VGM',
                'missing_vgm_container_count' => 3,
            ]);
            $this->fail('Expected DomainException on missing SOLAS VGM');
        } catch (DomainException $e) {
            $this->assertStringContainsString('SOLAS VGM safety gate blocked', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Fashion merchandising OTB breach');
        $action->execute([
            'call_code' => 'FSH-OTB-OVER',
            'otb_budget_ceiling_minor' => 200000000,
            'assortment_commit_minor' => 250000000,
        ]);
    }

    public function test_fase_184_to_186_digital_trust_hmac_ekyc_smart_district_and_30_line_contract_freeze(): void
    {
        $action = app(DigitalTrustVerifiableIdentityAndSmartDistrictGridAction::class);

        $res1 = $action->execute([
            'district_session_code' => 'DIST-FREEZE-185',
            'citizen_reference' => 'NIK-CITIZEN-8812',
            'liveness_score_bps' => 9400,
            'registered_business_lines_count' => 30,
            'breaking_event_schema_drift_count' => 0,
        ]);
        $res2 = $action->execute([
            'district_session_code' => 'DIST-FREEZE-185',
            'citizen_reference' => 'NIK-CITIZEN-8812',
            'liveness_score_bps' => 9400,
            'registered_business_lines_count' => 30,
            'breaking_event_schema_drift_count' => 0,
        ]);

        $this->assertStringStartsWith('DID-', $res1['identity_blind_index_hmac']);
        $this->assertSame($res1['identity_blind_index_hmac'], $res2['identity_blind_index_hmac']);
        $this->assertSame('FROZEN_30_LINES_ZERO_DRIFT', $res1['contract_freeze_status']);
        $this->assertSame($res1['value_chain_simulation_hash'], $res2['value_chain_simulation_hash']);
    }

    public function test_fase_187_to_200_30_line_treasury_sod_scale_ai_guardrails_and_fraud_aml_mesh(): void
    {
        $action = app(Enterprise30LinesMasterCommandAndRelease300HandoverAction::class);

        $res = $action->execute([
            'command_code' => 'W3-MESH-187-200',
            'sod_conflict_count' => 0,
            'p95_critical_latency_ms' => 115,
            'p95_budget_ms' => 250,
            'aml_fraud_risk_score_bps' => 8600,
        ]);

        $this->assertSame(30, $res['verified_business_lines_count']);
        $this->assertSame('QUARANTINED_STR_CASE_OPENED', $res['fraud_mesh_disposition']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Segregation of Duties (SoD) violation');
        $action->execute([
            'command_code' => 'W3-SOD-FAIL',
            'sod_conflict_count' => 2,
        ]);
    }
}
