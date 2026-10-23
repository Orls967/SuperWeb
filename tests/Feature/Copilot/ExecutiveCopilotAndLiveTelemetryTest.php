<?php

declare(strict_types=1);

namespace Tests\Feature\Copilot;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Modules\Core\Application\Copilot\CopilotToolResult;
use Modules\Core\Application\Copilot\ExecutiveCopilotEngine;
use Modules\Core\Application\Copilot\Tools\ArchitectureAndAuditIntegrityTool;
use Modules\Core\Application\Copilot\Tools\EnergyMiningAndSustainabilityTool;
use Modules\Core\Application\Copilot\Tools\HealthcareAndHospitalSafetyTool;
use Modules\Core\Application\Copilot\Tools\LedgerAndTreasuryHealthTool;
use Modules\Core\Application\Copilot\Tools\SupplyChainAndManufacturingPulseTool;
use Modules\Core\Application\Copilot\Tools\WhatIfScenarioSimulatorTool;
use Modules\Core\Application\Telemetry\LiveTelemetryStreamService;
use Tests\TestCase;

class ExecutiveCopilotAndLiveTelemetryTest extends TestCase
{
    /**
     * 1. Test LedgerAndTreasuryHealthTool executes deterministically with integer minor units.
     */
    public function test_ledger_and_treasury_health_tool_executes_deterministically_with_minor_units(): void
    {
        $tool = new LedgerAndTreasuryHealthTool;
        $this->assertSame('ledger_and_treasury_health', $tool->name());
        $this->assertSame('Banking/Treasury/Finance', $tool->moduleScope());
        $this->assertTrue($tool->canHandle('Analisa buku besar dan kas grup'));
        $this->assertTrue($tool->canHandle('General ledger debit credit balance'));

        $result = $tool->execute();
        $this->assertInstanceOf(CopilotToolResult::class, $result);
        $this->assertTrue($result->integrityVerified);
        $this->assertSame(0, $result->metrics['imbalance_minor_units']);
        $this->assertSame(142589000000000, $result->metrics['total_debit_minor_units']);
        $this->assertSame(142589000000000, $result->metrics['total_credit_minor_units']);
        $this->assertSame(148, $result->metrics['active_accounts_count']);
        $this->assertSame(10000, $result->metrics['fx_hedged_ratio_bps']);
        $this->assertNotEmpty($result->executionProofHash);
        $this->assertStringContainsString('keseimbangan sempurna', $result->summaryText);
    }

    /**
     * 2. Test ArchitectureAndAuditIntegrityTool verifies 46+ registered modules and zero violations.
     */
    public function test_architecture_and_audit_integrity_tool_verifies_modules_and_audit_commands(): void
    {
        $tool = new ArchitectureAndAuditIntegrityTool;
        $this->assertSame('architecture_and_audit_integrity', $tool->name());
        $this->assertSame('Quality/Core/Architecture', $tool->moduleScope());
        $this->assertTrue($tool->canHandle('Cek aturan arsitektur a1 sampai a13'));

        $result = $tool->execute();
        $this->assertTrue($result->integrityVerified);
        $this->assertGreaterThanOrEqual(46, $result->metrics['registered_modules_count']);
        $this->assertGreaterThanOrEqual(10, $result->metrics['audit_commands_count']);
        $this->assertSame(13, $result->metrics['architectural_rules_count']);
        $this->assertSame(0, $result->metrics['violations_count']);
        $this->assertSame(10000, $result->metrics['integrity_score_bps']);
        $this->assertStringContainsString('terverifikasi solid', $result->summaryText);
    }

    /**
     * 3. Test SupplyChainAndManufacturingPulseTool calculates WMS, OEE, and OTIF.
     */
    public function test_supply_chain_and_manufacturing_pulse_tool_calculates_wms_oee_otif(): void
    {
        $tool = new SupplyChainAndManufacturingPulseTool;
        $this->assertSame('supply_chain_and_manufacturing_pulse', $tool->name());
        $this->assertSame('ControlTower/WMS/Manufacturing/Logistics', $tool->moduleScope());
        $this->assertTrue($tool->canHandle('Pantau denyut rantai pasok dan utilisasi wms'));

        $result = $tool->execute();
        $this->assertTrue($result->integrityVerified);
        $this->assertSame(450000, $result->metrics['network_capacity_units']);
        $this->assertSame(8420, $result->metrics['wms_utilization_bps']);
        $this->assertSame(8870, $result->metrics['manufacturing_oee_bps']);
        $this->assertSame(9850, $result->metrics['logistics_otif_bps']);
        $this->assertSame(0, $result->metrics['backlog_orders_count']);
        $this->assertSame('OPTIMAL', $result->metrics['supply_chain_health']);
    }

    /**
     * 4. Test HealthcareAndHospitalSafetyTool verifies triage, cold-chain, and INA-CBG claims.
     */
    public function test_healthcare_and_hospital_safety_tool_verifies_triage_cold_chain_and_ina_cbg(): void
    {
        $tool = new HealthcareAndHospitalSafetyTool;
        $this->assertSame('healthcare_and_hospital_safety', $tool->name());
        $this->assertSame('Hospital/Healthcare', $tool->moduleScope());
        $this->assertTrue($tool->canHandle('Bagaimana status antrean triase IGD rumah sakit?'));

        $result = $tool->execute();
        $this->assertTrue($result->integrityVerified);
        $this->assertSame(6, $result->metrics['emergency_triage_queue']);
        $this->assertSame(7640, $result->metrics['bed_occupancy_rate_bps']);
        $this->assertSame(4150, $result->metrics['cold_chain_temp_milli_celsius']);
        $this->assertSame(1250, $result->metrics['ina_cbg_claims_verified_count']);
        $this->assertSame(1875000000000, $result->metrics['ina_cbg_reimbursement_minor_units']);
        $this->assertSame(9980, $result->metrics['clinical_safety_score_bps']);
    }

    /**
     * 5. Test EnergyMiningAndSustainabilityTool reports grid, weighbridge tonnage, and ESG metrics.
     */
    public function test_energy_mining_and_sustainability_tool_reports_grid_tonnage_and_esg(): void
    {
        $tool = new EnergyMiningAndSustainabilityTool;
        $this->assertSame('energy_mining_and_sustainability', $tool->name());
        $this->assertSame('Egy/Mining/Esg', $tool->moduleScope());
        $this->assertTrue($tool->canHandle('Status PPA smart grid dan rekonsiliasi tambang'));

        $result = $tool->execute();
        $this->assertTrue($result->integrityVerified);
        $this->assertSame(450, $result->metrics['smart_grid_load_mw']);
        $this->assertSame(600, $result->metrics['ppa_contracted_capacity_mw']);
        $this->assertSame(12000, $result->metrics['rec_carbon_credits_retired']);
        $this->assertSame(145000, $result->metrics['weighbridge_verified_tonnage']);
        $this->assertSame(9420, $result->metrics['smelter_recovery_rate_bps']);
        $this->assertSame(10000, $result->metrics['sustainability_compliance_bps']);
    }

    /**
     * 6. Test WhatIfScenarioSimulatorTool handles load surge 20% using integer arithmetic.
     */
    public function test_what_if_scenario_simulator_handles_load_surge_20_percent(): void
    {
        $tool = new WhatIfScenarioSimulatorTool;
        $this->assertSame('what_if_scenario_simulator', $tool->name());
        $this->assertSame('Core/Simulation/WhatIf', $tool->moduleScope());
        $this->assertTrue($tool->canHandle('Simulasikan lonjakan beban 20%'));

        $result = $tool->execute(['scenario' => 'lonjakan beban 20%']);
        $this->assertTrue($result->integrityVerified);
        $this->assertSame('Lonjakan Beban Operasional & Jaringan 20%', $result->metrics['scenario_identified']);
        $this->assertSame(2000, $result->metrics['load_surge_bps']);
        $this->assertSame(-1572500000000, $result->metrics['projected_ebitda_impact_minor_units']);
        $this->assertSame(62712500000000, $result->metrics['liquidity_buffer_headroom_minor_units']);
        $this->assertSame(2200, $result->metrics['recommended_reserve_buffer_bps']);
        $this->assertSame(3, $result->metrics['mitigation_actions_count']);
    }

    /**
     * 7. Test WhatIfScenarioSimulatorTool handles FX depreciation and 3x demand shock.
     */
    public function test_what_if_scenario_simulator_handles_fx_depreciation_and_demand_surge(): void
    {
        $tool = new WhatIfScenarioSimulatorTool;

        // 7.1 FX Depreciation 500 bps
        $fxResult = $tool->execute(['scenario' => 'depresiasi kurs 500 bps']);
        $this->assertTrue($fxResult->integrityVerified);
        $this->assertSame(500, $fxResult->metrics['load_surge_bps']);
        $this->assertSame(-925000000000, $fxResult->metrics['projected_ebitda_impact_minor_units']);
        $this->assertSame(1500, $fxResult->metrics['recommended_reserve_buffer_bps']);

        // 7.2 Demand surge 3x
        $demandResult = $tool->execute(['scenario' => 'lonjakan permintaan 3x']);
        $this->assertTrue($demandResult->integrityVerified);
        $this->assertSame(30000, $demandResult->metrics['load_surge_bps']);
        $this->assertGreaterThan(0, $demandResult->metrics['projected_ebitda_impact_minor_units']);
        $this->assertSame(3000, $demandResult->metrics['recommended_reserve_buffer_bps']);
    }

    /**
     * 8. Test ExecutiveCopilotEngine routes queries and maps tools deterministically.
     */
    public function test_executive_copilot_engine_routes_multilingual_natural_language_queries(): void
    {
        $engine = app(ExecutiveCopilotEngine::class);

        // Indonesian query for hospital
        $respHospital = $engine->ask('Bagaimana kondisi triase dan cold chain rumah sakit?');
        $this->assertTrue($respHospital->integrityVerified);
        $this->assertNotEmpty($respHospital->toolResults);
        $this->assertSame('healthcare_and_hospital_safety', $respHospital->toolResults[0]->toolName);

        // English query for ledger & treasury
        $respLedger = $engine->ask('Check general ledger balance and group treasury cash pool');
        $this->assertTrue($respLedger->integrityVerified);
        $this->assertNotEmpty($respLedger->toolResults);
        $this->assertSame('ledger_and_treasury_health', $respLedger->toolResults[0]->toolName);
    }

    /**
     * 9. Test ExecutiveCopilotEngine executes multi-domain synthesis with overall proof hash.
     */
    public function test_executive_copilot_engine_executes_multidomain_synthesis_with_overall_proof_hash(): void
    {
        $engine = app(ExecutiveCopilotEngine::class);
        $prompt = 'Analisa integritas buku besar, rantai pasok, rumah sakit, dan simulasi skenario lonjakan beban 20%';

        $response = $engine->ask($prompt);

        $this->assertTrue($response->integrityVerified);
        $this->assertGreaterThanOrEqual(4, count($response->toolResults));
        $this->assertNotEmpty($response->overallProofHash);
        $this->assertSame(64, strlen($response->overallProofHash), 'HMAC-SHA256 must be exactly 64 hex characters');

        // Verify key tools were included in synthesis
        $executedToolNames = array_map(fn ($r) => $r->toolName, $response->toolResults);
        $this->assertContains('ledger_and_treasury_health', $executedToolNames);
        $this->assertContains('supply_chain_and_manufacturing_pulse', $executedToolNames);
        $this->assertContains('healthcare_and_hospital_safety', $executedToolNames);
        $this->assertContains('what_if_scenario_simulator', $executedToolNames);

        // Verify KPI table contains cross-module keys
        $this->assertArrayHasKey('total_debit_minor_units', $response->kpiTable);
        $this->assertArrayHasKey('logistics_otif_bps', $response->kpiTable);
        $this->assertArrayHasKey('cold_chain_temp_milli_celsius', $response->kpiTable);
        $this->assertArrayHasKey('liquidity_buffer_headroom_minor_units', $response->kpiTable);

        // Verify markdown output structure
        $markdown = $response->toMarkdown();
        $this->assertStringContainsString('ENTERPRISE AI EXECUTIVE COPILOT', $markdown);
        $this->assertStringContainsString('Master Execution Proof Hash', $markdown);
        $this->assertStringContainsString('Tabel Konsolidasi Indikator Kunci (KPI)', $markdown);
    }

    /**
     * 10. Test CopilotToolResult proof hash is deterministic and tamper-evident.
     */
    public function test_copilot_tool_result_proof_hash_is_deterministic_hmac_sha256(): void
    {
        $metrics = ['debit' => 1000, 'credit' => 1000];
        $result1 = CopilotToolResult::create('test_tool', 'TestScope', 'Test summary', $metrics, true, 'secret-key-1');
        $result2 = CopilotToolResult::create('test_tool', 'TestScope', 'Test summary', $metrics, true, 'secret-key-1');
        $result3 = CopilotToolResult::create('test_tool', 'TestScope', 'Different summary', $metrics, true, 'secret-key-1');

        $this->assertSame($result1->executionProofHash, $result2->executionProofHash, 'Identical payload and key must generate identical hash');
        $this->assertNotSame($result1->executionProofHash, $result3->executionProofHash, 'Different payload must generate different hash');
    }

    /**
     * 11. Test LiveTelemetryStreamService generates snapshot for all 6 command centers.
     */
    public function test_live_telemetry_stream_service_generates_snapshot_for_all_6_command_centers(): void
    {
        $service = app(LiveTelemetryStreamService::class);
        $snapshot = $service->getSnapshot();

        $this->assertArrayHasKey('stream_id', $snapshot);
        $this->assertArrayHasKey('timestamp', $snapshot);
        $this->assertArrayHasKey('command_centers', $snapshot);
        $this->assertSame(6, $snapshot['command_centers_count']);
        $this->assertNotEmpty($snapshot['checksum']);

        $centers = $snapshot['command_centers'];
        $expectedKeys = [
            'control_tower',
            'smart_grid_egy',
            'hospital_igd',
            'mining_dispatch',
            'rwa_orderbook',
            'telecom_5g_tlx',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $centers, "Snapshot harus memuat pusat komando {$key}");
            $this->assertNotEmpty($centers[$key]['name']);
            $this->assertNotEmpty($centers[$key]['status']);
        }

        // Specific metrics check
        $this->assertSame(9850, $centers['control_tower']['otif_bps']);
        $this->assertSame(450, $centers['smart_grid_egy']['grid_load_mw']);
        $this->assertSame(4150, $centers['hospital_igd']['cold_chain_temp_milli_celsius']);
        $this->assertSame(145000, $centers['mining_dispatch']['weighbridge_verified_tonnage']);
        $this->assertSame(10000, $centers['rwa_orderbook']['escrow_parity_bps']);
        $this->assertSame(12, $centers['telecom_5g_tlx']['active_5g_slices_count']);
    }

    /**
     * 12. Test LiveTelemetryStreamService formats valid SSE protocol stream.
     */
    public function test_live_telemetry_stream_service_formats_valid_sse_stream(): void
    {
        $service = app(LiveTelemetryStreamService::class);
        $sse = $service->toSseStream(eventsCount: 3);

        $this->assertStringContainsString('event: telemetry', $sse);
        $this->assertStringContainsString('id: stream_', $sse);
        $this->assertStringContainsString('data: {"stream_id":', $sse);
        $this->assertStringContainsString('"command_centers":', $sse);

        // Count occurrences of event: telemetry
        $this->assertSame(3, substr_count($sse, 'event: telemetry'));
    }

    /**
     * 13. Test Artisan copilot:ask command outputs formatted report with exit code 0.
     */
    public function test_artisan_copilot_ask_command_outputs_formatted_report_with_exit_code_0(): void
    {
        $exitCode = Artisan::call('copilot:ask', [
            'prompt' => 'Analisa integritas buku besar dan simulasi lonjakan beban 20%',
            '--scenario' => 'lonjakan beban 20%',
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('ENTERPRISE AI EXECUTIVE COPILOT', $output);
        $this->assertStringContainsString('RINGKASAN EKSEKUTIF', $output);
        $this->assertStringContainsString('TABEL KONSOLIDASI KPI', $output);
        $this->assertStringContainsString('Status Integritas : TERVERIFIKASI INTEGRITAS 100%', $output);
        $this->assertStringContainsString('Master Proof Hash', $output);
    }

    /**
     * 14. Test Artisan telemetry:snapshot command supports JSON and SSE formats.
     */
    public function test_artisan_telemetry_snapshot_command_supports_json_and_sse_formats(): void
    {
        // 14.1 JSON format
        $exitJson = Artisan::call('telemetry:snapshot', ['--format' => 'json']);
        $outputJson = Artisan::output();
        $this->assertSame(0, $exitJson);
        $decoded = json_decode($outputJson, true);
        $this->assertIsArray($decoded);
        $this->assertSame(6, $decoded['command_centers_count']);

        // 14.2 SSE format
        $exitSse = Artisan::call('telemetry:snapshot', ['--format' => 'sse']);
        $outputSse = Artisan::output();
        $this->assertSame(0, $exitSse);
        $this->assertStringContainsString('event: telemetry', $outputSse);
        $this->assertStringContainsString('data: {', $outputSse);
    }

    /**
     * 15. Test Blade live telemetry ticker component renders all 6 centers.
     */
    public function test_blade_live_telemetry_ticker_component_renders_all_6_centers(): void
    {
        $rendered = Blade::render('<x-enterprise.live-telemetry-ticker />');

        $this->assertStringContainsString('enterprise-live-telemetry-ticker-container', $rendered);
        $this->assertStringContainsString('Live Telemetry 6 Command Center (SSE Stream)', $rendered);
        $this->assertStringContainsString('data-center-id="control_tower"', $rendered);
        $this->assertStringContainsString('data-center-id="smart_grid_egy"', $rendered);
        $this->assertStringContainsString('data-center-id="hospital_igd"', $rendered);
        $this->assertStringContainsString('data-center-id="mining_dispatch"', $rendered);
        $this->assertStringContainsString('data-center-id="rwa_orderbook"', $rendered);
        $this->assertStringContainsString('data-center-id="telecom_5g_tlx"', $rendered);
    }

    /**
     * 16. Test Blade command palette integrates telemetry ticker and copilot prompt box.
     */
    public function test_blade_command_palette_integrates_telemetry_ticker_and_copilot_prompt_box(): void
    {
        $rendered = Blade::render('<x-enterprise.command-palette />');

        $this->assertStringContainsString('enterprise-command-palette-wrapper', $rendered);
        $this->assertStringContainsString('enterprise-live-telemetry-ticker-container', $rendered);
        $this->assertStringContainsString('data-copilot-prompt-box="true"', $rendered);
        $this->assertStringContainsString('Enterprise AI Executive Copilot (Deterministic Tool-Calling)', $rendered);
        $this->assertStringContainsString('Buku Besar & Kas', $rendered);
        $this->assertStringContainsString('What-If Lonjakan 20%', $rendered);
        $this->assertStringContainsString('IGD & Cold-Chain', $rendered);
    }
}
