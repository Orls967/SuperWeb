<?php

declare(strict_types=1);

namespace Modules\Tlx\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Tlx\Application\Actions\MeterDataCenterColocationPowerAndLiquidCoolingSlaAction;
use Modules\Tlx\Application\Actions\OrchestrateSmartCityIot5GSliceAndFttxBillingAction;
use Modules\Tlx\Application\Actions\RouteBackbonePeeringAndBgpDdosMitigationAction;
use Tests\TestCase;

class Fase130To132TelecomDataCenterExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_130_backbone_capacity_oversubscription_rejection(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('capacity oversubscription rejected');

        $action = app(RouteBackbonePeeringAndBgpDdosMitigationAction::class);
        $action->execute([
            'routing_code' => 'BGP-FAIL-01',
            'link_id' => 'LINK-CORE-01',
            'capacity_gbps' => 100,
            'requested_traffic_gbps' => 120, // Exceeds 100 Gbps
        ]);
    }

    public function test_fase_130_bgp_ddos_mitigation_and_iot_connectivity_billing(): void
    {
        $action = app(RouteBackbonePeeringAndBgpDdosMitigationAction::class);

        $params = [
            'routing_code' => 'BGP-ROUTE-OK-01',
            'link_id' => 'LINK-CORP-JKT-SBY',
            'capacity_gbps' => 200,
            'requested_traffic_gbps' => 150,
            'ddos_threat_level' => 'HIGH',
            'sla_uptime_pct' => 99,
            'iot_devices' => [
                ['device_id' => 'DEV-FLEET-001', 'entity_id' => 'ENT-LOGISTICS', 'line_name' => 'Logistics', 'mb_consumed' => 200, 'rate_per_mb_minor' => 500],
                ['device_id' => 'DEV-MINING-002', 'entity_id' => 'ENT-MINING', 'line_name' => 'Mining', 'mb_consumed' => 500, 'rate_per_mb_minor' => 500],
            ],
        ];

        $res = $action->execute($params);

        $this->assertEquals(50, $res['available_headroom_gbps']);
        $this->assertNotNull($res['mitigation_ticket_id']);
        $this->assertTrue($res['sla_compliant']);
        $this->assertEquals(350000, $res['total_iot_billed_minor']); // (200 * 500) + (500 * 500) = 100k + 250k

        $tx = LedgerTransaction::where('reference_id', 'BGP-ROUTE-OK-01')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_131_datacenter_pue_cooling_sla_and_colocation_overdue_suspension(): void
    {
        $action = app(MeterDataCenterColocationPowerAndLiquidCoolingSlaAction::class);

        // Scenario: PUE exceeds SLA (5400 / 3600 = 1.50 > 1.40) & status is OVERDUE
        $params = [
            'operation_code' => 'DC-SLA-TEST-01',
            'dc_site_id' => 'DC-SG-01',
            'total_facility_power_kw' => 5400,
            'it_equipment_load_kw' => 3600,
            'sla_max_pue_basis_points' => 140,
            'colocation_fee_minor' => 100000000,
            'pue_penalty_rate_pct' => 10,
            'payment_status' => 'OVERDUE',
        ];

        $res = $action->execute($params);

        $this->assertEquals(150, $res['pue_basis_points']);
        $this->assertTrue($res['pue_violation']);
        $this->assertEquals('SUSPENDED', $res['colocation_port_status']);
        $this->assertEquals(10000000, $res['penalty_credit_minor']); // 10% penalty
    }

    public function test_fase_131_internal_cloud_chargeback_and_dr_restore_zero_rpo_verification(): void
    {
        $action = app(MeterDataCenterColocationPowerAndLiquidCoolingSlaAction::class);

        $params = [
            'operation_code' => 'DC-CLOUD-TEST-02',
            'dc_site_id' => 'DC-JKT-01',
            'total_facility_power_kw' => 4800,
            'it_equipment_load_kw' => 4000, // PUE = 1.20 <= 1.40
            'colocation_fee_minor' => 200000000,
            'payment_status' => 'PAID',
            'cloud_instances' => [
                ['instance_id' => 'VM-AI-CORE', 'entity_id' => 'ENT-AI-LAB', 'vcpu_hours' => 1000, 'rate_per_vcpu_minor' => 15000],
                ['instance_id' => 'VM-MED-APP', 'entity_id' => 'ENT-MEDIA', 'vcpu_hours' => 500, 'rate_per_vcpu_minor' => 10000],
            ],
            'dr_rpo_status' => 'ZERO_DATA_LOSS',
            'dr_rto_minutes' => 10,
        ];

        $res = $action->execute($params);

        $this->assertEquals(120, $res['pue_basis_points']);
        $this->assertFalse($res['pue_violation']);
        $this->assertEquals('ACTIVE', $res['colocation_port_status']);
        $this->assertEquals(20000000, $res['total_cloud_chargeback_minor']); // 15M + 5M
        $this->assertTrue($res['dr_compliant']);

        $tx = LedgerTransaction::where('reference_id', 'DC-CLOUD-TEST-02')->where('type', 'TELECOM_CLOUD_COMPUTE_CHARGEBACK')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_132_isp_usage_cap_throttling_and_unpaid_renewal_pause(): void
    {
        $action = app(OrchestrateSmartCityIot5GSliceAndFttxBillingAction::class);

        // Usage 120GB > 100GB (throttled) and balance 0 < 300k (paused)
        $params = [
            'operation_code' => 'ISP-CAP-TEST-01',
            'slice_monthly_fee_minor' => 0,
            'subscriber_usage_gb' => 120,
            'subscriber_quota_gb' => 100,
            'subscriber_balance_minor' => 0,
            'plan_renewal_cost_minor' => 300000,
        ];

        $res = $action->execute($params);

        $this->assertTrue($res['is_throttled']);
        $this->assertEquals('PAUSED_INSUFFICIENT_FUNDS', $res['subscription_status']);
    }

    public function test_fase_132_smart_city_5g_slice_and_interconnect_roaming_settlement(): void
    {
        $action = app(OrchestrateSmartCityIot5GSliceAndFttxBillingAction::class);

        $params = [
            'operation_code' => '5G-SLICE-TEST-02',
            'slice_id' => 'SLICE-TRAFFIC-AI-01',
            'slice_monthly_fee_minor' => 75000000,
            'subscriber_usage_gb' => 50,
            'subscriber_quota_gb' => 100,
            'subscriber_balance_minor' => 500000,
            'plan_renewal_cost_minor' => 300000,
            'inbound_traffic_mb' => 80000,
            'outbound_traffic_mb' => 50000,
            'rate_per_mb_minor' => 20,
            'open_complaint_tickets' => 1,
            'usage_drop_pct' => 5,
        ];

        $res = $action->execute($params);

        $this->assertFalse($res['is_throttled']);
        $this->assertEquals('ACTIVE', $res['subscription_status']);
        $this->assertEquals(600000, $res['net_interconnect_minor']); // (80k - 50k) * 20 = 600,000
        $this->assertEquals(25, $res['churn_risk_score']); // (1 * 20) + 5 = 25

        $tx = LedgerTransaction::where('reference_id', '5G-SLICE-TEST-02')->where('type', 'TELECOM_5G_SLICE_PROVISION')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }
}
