<?php

declare(strict_types=1);

namespace Modules\ControlTower\database\seeders;

use Illuminate\Database\Seeder;
use Modules\ControlTower\Domain\Models\AlertRule;
use Modules\ControlTower\Domain\Models\ControlTowerKpiSnapshot;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\InventoryHealthSnapshot;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Models\OrderRiskScore;
use Modules\ControlTower\Domain\Models\SafetyStockPolicy;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\SupplierScorecardSnapshot;
use Modules\ControlTower\Domain\Models\TwinScenario;

class ControlTowerSeeder extends Seeder
{
    public function run(): void
    {
        $echelons = [
            ['item_code' => 'SP-001', 'item_name' => 'Oli Mesin Castrol 10W-40', 'echelon_node' => 'PLANT', 'on_hand_qty' => 240, 'in_transit_qty' => 60, 'reserved_qty' => 40, 'safety_stock_qty' => 50, 'abc_class' => 'A', 'xyz_class' => 'X'],
            ['item_code' => 'SP-001', 'item_name' => 'Oli Mesin Castrol 10W-40', 'echelon_node' => 'DC', 'on_hand_qty' => 150, 'in_transit_qty' => 50, 'reserved_qty' => 30, 'safety_stock_qty' => 40, 'abc_class' => 'A', 'xyz_class' => 'X'],
            ['item_code' => 'SP-001', 'item_name' => 'Oli Mesin Castrol 10W-40', 'echelon_node' => 'OUTLET', 'on_hand_qty' => 80, 'in_transit_qty' => 20, 'reserved_qty' => 10, 'safety_stock_qty' => 25, 'abc_class' => 'A', 'xyz_class' => 'X'],
            ['item_code' => 'SP-004', 'item_name' => 'Kampas Rem Depan', 'echelon_node' => 'DC', 'on_hand_qty' => 120, 'in_transit_qty' => 30, 'reserved_qty' => 20, 'safety_stock_qty' => 30, 'abc_class' => 'B', 'xyz_class' => 'Y'],
        ];

        foreach ($echelons as $row) {
            EchelonStock::firstOrCreate(
                ['item_code' => $row['item_code'], 'echelon_node' => $row['echelon_node']],
                $row,
            );
        }

        SafetyStockPolicy::firstOrCreate(
            ['item_code' => 'SP-001'],
            [
                'abc_class' => 'A',
                'xyz_class' => 'X',
                'service_level_bps' => 9900,
                'safety_stock_qty' => 115,
                'reorder_point_qty' => 260,
                'slow_moving_flag' => false,
                'dead_stock_flag' => false,
                'status' => 'active',
                'supplier_ref' => 'SUP-SEED-001',
                'lead_time_mean_days' => 5,
                'lead_time_sigma_days' => 1,
                'echelon_breakdown' => ['PLANT' => 50, 'DC' => 40, 'OUTLET' => 25],
            ],
        );

        SopCycle::firstOrCreate(
            ['cycle_code' => 'SOP-2026-11'],
            [
                'period' => '2026-11',
                'status' => 'pre_sop',
                'scenario_type' => 'moderate',
                'demand_plan_qty' => 1200,
                'supply_plan_qty' => 1260,
                'capacity_utilization_bps' => 8400,
                'gross_margin_est_idr' => 145000000,
                'cash_impact_est_idr' => 32000000,
            ],
        );

        DemandForecast::firstOrCreate(
            ['forecast_code' => 'FC-2026-11-SP001'],
            [
                'item_code' => 'SP-001',
                'period' => '2026-11',
                'algorithm_model' => 'HOLT',
                'forecast_qty' => 185,
                'actual_qty' => 180,
                'mae_scaled' => 32500,
                'is_overridden' => false,
            ],
        );

        OrderPromise::firstOrCreate(
            ['promise_code' => 'PRM-SEED-001'],
            [
                'order_reference' => 'SO-SEED-001',
                'item_code' => 'SP-001',
                'requested_qty' => 45,
                'atp_confirmed_qty' => 45,
                'ctp_manufacturing_qty' => 0,
                'promised_delivery_date' => '2026-11-18',
                'promise_status' => 'confirmed',
                'reason_code' => 'ATP_SUFFICIENT_ON_TIME',
                'promised_period' => 0,
                'late_periods' => 0,
                'as_of_date' => '2026-11-14',
            ],
        );

        AlertRule::firstOrCreate(
            ['rule_code' => 'RULE-STOCKOUT-HIGH'],
            [
                'category' => 'INVENTORY_SHORTFALL',
                'severity' => 'HIGH',
                'threshold_bps' => 2000,
                'is_active' => true,
            ],
        );

        DisruptionAlert::firstOrCreate(
            ['alert_code' => 'ALT-SEED-001'],
            [
                'dedup_key' => 'seed:port_congestion:2026-11-14',
                'severity' => 'MEDIUM',
                'category' => 'PORT_CONGESTION',
                'title' => 'Antrean Dermaga Feeder Surabaya',
                'description' => 'Estimasi keterlambatan bongkar muat 18 jam untuk jalur laut timur.',
                'affected_orders_count' => 2,
                'status' => 'active',
            ],
        );

        OrderRiskScore::firstOrCreate(
            ['item_code' => 'SP-004', 'order_reference' => 'SO-SEED-002'],
            [
                'risk_score' => 420,
                'risk_tier' => 'MEDIUM',
                'shortfall_component' => 150,
                'lead_time_component' => 120,
                'shelf_life_component' => 50,
                'transit_component' => 100,
                'factors_json' => ['shortfall_bps' => 1200, 'lead_time_sigma_days' => 2],
                'mitigation_recommendation' => 'Monitor replenishment PO menuju DC-BJM.',
                'scored_at' => '2026-11-14 08:00:00',
            ],
        );

        SupplierScorecardSnapshot::firstOrCreate(
            ['supplier_ref' => 'SUP-SEED-001', 'period' => '2026-11'],
            [
                'supplier_name' => 'PT Pelumas Nusantara Sejahtera',
                'composite_score' => 865,
                'otd_score' => 900,
                'reliability_score' => 850,
                'volume_score' => 820,
                'on_time_permil' => 920,
                'mean_lead_time_days' => 5,
                'sigma_lead_time_days' => 1,
                'samples_count' => 14,
                'tier' => 'STRATEGIC',
                'trend_json' => ['direction' => 'UP', 'months' => []],
                'action_recommendation' => 'Pertahankan alokasi volume utama.',
                'computed_at' => '2026-11-14 08:00:00',
            ],
        );

        InventoryHealthSnapshot::firstOrCreate(
            ['item_code' => 'SP-001', 'echelon_node' => 'DC', 'snapshot_date' => '2026-11-14'],
            [
                'health_status' => 'HEALTHY',
                'aging_bucket' => '0-30',
                'on_hand_qty' => 150,
                'quarantine_qty' => 0,
                'available_qty' => 120,
                'daily_demand_qty' => 6,
                'dos_days' => 20,
                'oldest_lot_age_days' => 14,
                'tied_capital_idr' => 12750000,
                'obsolescence_risk_idr' => 0,
            ],
        );

        ControlTowerKpiSnapshot::firstOrCreate(
            ['snapshot_date' => '2026-11-14'],
            [
                'otif_bps' => 9450,
                'cash_to_cash_days' => 28,
                'inventory_dos_days' => 22,
                'fulfillment_lead_time_hours' => 36,
                'logistics_cost_ratio_bps' => 480,
            ],
        );

        TwinScenario::firstOrCreate(
            ['scenario_code' => 'TWIN-SEED-DEMAND15'],
            [
                'name' => 'Simulasi Lonjakan Permintaan 15%',
                'shock_type' => 'DEMAND_SURGE',
                'shock_magnitude_bps' => 1500,
                'duration_days' => 14,
                'baseline_otif_bps' => 9450,
                'simulated_otif_bps' => 8920,
                'at_risk_orders_count' => 3,
                'estimated_margin_delta_idr' => -4500000,
                'status' => 'completed',
            ],
        );
    }
}
