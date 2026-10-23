<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Application\Actions\AutomatedDemurrageSettlementAction;
use Modules\Logistics\Application\Actions\MultimodalLoadConsolidationAction;
use Tests\TestCase;

class LogisticsAdvancedOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_multimodal_load_consolidation_success(): void
    {
        $action = app(MultimodalLoadConsolidationAction::class);

        $shipments = [
            ['shipment_id' => 'SHP-001', 'weight_kg' => 4500, 'volume_cbm' => 12.5, 'freight_value_idr' => 15000000],
            ['shipment_id' => 'SHP-002', 'weight_kg' => 8000, 'volume_cbm' => 22.0, 'freight_value_idr' => 28000000],
            ['shipment_id' => 'SHP-003', 'weight_kg' => 6000, 'volume_cbm' => 16.5, 'freight_value_idr' => 21000000],
        ];

        $manifest = $action->execute([
            'max_weight_kg' => 24000,
            'max_volume_cbm' => 60.0,
            'corridor' => 'JKT-SUB-INTERMODAL',
            'shipments' => $shipments,
        ]);

        $this->assertSame(3, $manifest['shipments_count']);
        $this->assertSame(18500, $manifest['total_weight_kg']);
        $this->assertSame(51.0, $manifest['total_volume_cbm']);
        $this->assertSame(64000000, $manifest['total_freight_value_idr']);
        $this->assertSame(77.08, $manifest['weight_utilization_percent']);
        $this->assertSame(85.0, $manifest['volume_utilization_percent']);
        $this->assertSame('consolidated_ready_for_dispatch', $manifest['status']);
    }

    public function test_multimodal_load_consolidation_rejects_weight_overload(): void
    {
        $action = app(MultimodalLoadConsolidationAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Kapasitas berat melebihi batas maksimum (10000 kg).');

        $action->execute([
            'max_weight_kg' => 10000,
            'max_volume_cbm' => 50.0,
            'shipments' => [
                ['shipment_id' => 'SHP-HEAVY-1', 'weight_kg' => 7000, 'volume_cbm' => 10.0, 'freight_value_idr' => 10000000],
                ['shipment_id' => 'SHP-HEAVY-2', 'weight_kg' => 4000, 'volume_cbm' => 10.0, 'freight_value_idr' => 10000000],
            ],
        ]);
    }

    public function test_automated_demurrage_settlement_exempt_within_free_window(): void
    {
        $action = app(AutomatedDemurrageSettlementAction::class);

        // Dwell 4 days, free allowance 5 days
        $result = $action->execute([
            'container_number' => 'MSCU-1234567',
            'dwell_days' => 4,
            'free_days_allowed' => 5,
            'daily_demurrage_rate_idr' => 750000,
        ]);

        $this->assertSame(0, $result['billable_demurrage_days']);
        $this->assertSame(0, $result['total_demurrage_fee_idr']);
        $this->assertSame('exempt_within_free_window', $result['status']);
    }

    public function test_automated_demurrage_settlement_billable_assessed(): void
    {
        $action = app(AutomatedDemurrageSettlementAction::class);

        // Dwell 9 days, free allowance 5 days => 4 billable days * 800k = 3.2M
        $result = $action->execute([
            'container_number' => 'CMAU-9876543',
            'dwell_days' => 9,
            'free_days_allowed' => 5,
            'daily_demurrage_rate_idr' => 800000,
        ]);

        $this->assertSame(4, $result['billable_demurrage_days']);
        $this->assertSame(3200000, $result['total_demurrage_fee_idr']);
        $this->assertSame('billable_assessed', $result['status']);
    }
}
