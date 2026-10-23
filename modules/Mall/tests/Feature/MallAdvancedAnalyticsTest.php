<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mall\Application\Actions\TenantRevenueShareTierAction;
use Modules\Mall\Application\Actions\UtilitySubmeterAnomalyAction;
use Tests\TestCase;

class MallAdvancedAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_revenue_share_tier_when_turnover_exceeds_base_rent(): void
    {
        $action = app(TenantRevenueShareTierAction::class);

        // Sales: 350M
        // Tier 1: 5% of 100M = 5M
        // Tier 2: 7% of 200M (between 100M-300M) = 14M
        // Tier 3: 10% of 50M (above 300M) = 5M
        // Total variable rent = 24M. Base rent = 20M.
        // Effective billed = 24M (turnover percentage applied).
        $result = $action->execute([
            'tenant_id' => 101,
            'gross_sales_idr' => 350000000,
            'base_rent_idr' => 20000000,
        ]);

        $this->assertSame(24000000, $result['total_variable_rent_idr']);
        $this->assertSame(24000000, $result['effective_billed_rent_idr']);
        $this->assertSame('turnover_percentage_applied', $result['rule_applied']);
    }

    public function test_tenant_revenue_share_tier_when_base_rent_floor_applies(): void
    {
        $action = app(TenantRevenueShareTierAction::class);

        // Sales: 80M
        // 5% of 80M = 4M. Base rent = 15M.
        // Effective billed = 15M (guaranteed base rent floor applied).
        $result = $action->execute([
            'tenant_id' => 102,
            'gross_sales_idr' => 80000000,
            'base_rent_idr' => 15000000,
        ]);

        $this->assertSame(4000000, $result['total_variable_rent_idr']);
        $this->assertSame(15000000, $result['effective_billed_rent_idr']);
        $this->assertSame('guaranteed_base_rent_applied', $result['rule_applied']);
    }

    public function test_utility_submeter_anomaly_excessive_spike_detected(): void
    {
        $action = app(UtilitySubmeterAnomalyAction::class);

        // Historical avg: 500 kWh. Previous: 1000, Current: 2300 => Consumption = 1300 kWh (+160% spike)
        $result = $action->execute([
            'meter_number' => 'MTR-ELEC-T101',
            'utility_type' => 'electricity',
            'previous_reading' => 1000,
            'current_reading' => 2300,
            'historical_average_consumption' => 500,
        ]);

        $this->assertTrue($result['is_anomaly']);
        $this->assertSame('high', $result['severity']);
        $this->assertSame('excessive_consumption_spike', $result['anomaly_type']);
        $this->assertSame(160.0, $result['deviation_percentage']);
    }

    public function test_utility_submeter_anomaly_negative_rollback_detected(): void
    {
        $action = app(UtilitySubmeterAnomalyAction::class);

        // Current reading lower than previous reading (tampered or replaced meter)
        $result = $action->execute([
            'meter_number' => 'MTR-WATER-T102',
            'utility_type' => 'water',
            'previous_reading' => 500,
            'current_reading' => 450,
            'historical_average_consumption' => 40,
        ]);

        $this->assertTrue($result['is_anomaly']);
        $this->assertSame('critical', $result['severity']);
        $this->assertSame('negative_meter_rollback', $result['anomaly_type']);
    }
}
