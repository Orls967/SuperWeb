<?php

declare(strict_types=1);

namespace Modules\Resto\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Resto\Application\Actions\CentralKitchenYieldVarianceAction;
use Modules\Resto\Application\Actions\MenuEngineeringMatrixAction;
use Tests\TestCase;

class RestoAdvancedAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_engineering_matrix_classification(): void
    {
        $action = app(MenuEngineeringMatrixAction::class);

        $items = [
            // High Volume, High Margin -> Star
            ['id' => 1, 'name' => 'Rendang Sapi Daging Pilihan', 'sales_volume' => 100, 'selling_price_idr' => 35000, 'cogs_idr' => 15000],
            // High Volume, Low Margin -> Plowhorse
            ['id' => 2, 'name' => 'Es Teh Manis Jumbo', 'sales_volume' => 150, 'selling_price_idr' => 8000, 'cogs_idr' => 6000],
            // Low Volume, High Margin -> Puzzle
            ['id' => 3, 'name' => 'Gulai Kepala Kakap Spesial', 'sales_volume' => 20, 'selling_price_idr' => 95000, 'cogs_idr' => 45000],
            // Low Volume, Low Margin -> Dog
            ['id' => 4, 'name' => 'Perkedel Kentang Mini', 'sales_volume' => 15, 'selling_price_idr' => 7000, 'cogs_idr' => 5500],
        ];

        $matrix = $action->execute(['items' => $items]);

        $this->assertSame(4, $matrix['total_items']);
        $this->assertSame(285, $matrix['total_units_sold']);
        $this->assertNotEmpty($matrix['quadrants']['stars']);
        $this->assertNotEmpty($matrix['quadrants']['plowhorses']);
        $this->assertNotEmpty($matrix['quadrants']['puzzles']);
        $this->assertNotEmpty($matrix['quadrants']['dogs']);

        $this->assertSame('Rendang Sapi Daging Pilihan', $matrix['quadrants']['stars'][0]['name']);
        $this->assertSame('Es Teh Manis Jumbo', $matrix['quadrants']['plowhorses'][0]['name']);
        $this->assertSame('Gulai Kepala Kakap Spesial', $matrix['quadrants']['puzzles'][0]['name']);
        $this->assertSame('Perkedel Kentang Mini', $matrix['quadrants']['dogs'][0]['name']);
    }

    public function test_central_kitchen_yield_variance_favorable(): void
    {
        $action = app(CentralKitchenYieldVarianceAction::class);

        // Expected 100 kg rendang paste, actual produced 105 kg
        $result = $action->execute([
            'batch_number' => 'CK-RENDANG-202610-01',
            'expected_yield_units' => 100,
            'actual_yield_units' => 105,
            'standard_cost_per_unit_idr' => 50000,
        ]);

        $this->assertSame(105.0, $result['yield_percentage']);
        $this->assertSame(5, $result['variance_units']);
        $this->assertSame(250000, $result['variance_cost_idr']);
        $this->assertSame('favorable', $result['classification']);
    }

    public function test_central_kitchen_yield_variance_unfavorable(): void
    {
        $action = app(CentralKitchenYieldVarianceAction::class);

        // Expected 200 portions, actual produced 180 portions (burn/spill)
        $result = $action->execute([
            'batch_number' => 'CK-GULAI-202610-02',
            'expected_yield_units' => 200,
            'actual_yield_units' => 180,
            'standard_cost_per_unit_idr' => 20000,
        ]);

        $this->assertSame(90.0, $result['yield_percentage']);
        $this->assertSame(-20, $result['variance_units']);
        $this->assertSame(-400000, $result['variance_cost_idr']);
        $this->assertSame('unfavorable', $result['classification']);
    }
}
