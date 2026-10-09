<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\FoodBrandNutritionService;
use Tests\TestCase;

/**
 * Fase 169 — Food Brand, Private Label & Nutrition Programs Tests
 *
 * Covers:
 *  (a) released formulation immutable
 *  (b) allergen conflict blocks order
 *  (c) private-label ownership separated
 *  (d) FEFO selection picks earliest expiration date
 *  (e) food:audit reconciles inventory and health
 */
class FoodBrandNutritionTest extends TestCase
{
    use RefreshDatabase;

    protected FoodBrandNutritionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FoodBrandNutritionService::class);
    }

    /**
     * (a) Formulation created with immutability flag.
     */
    public function test_formulation_immutability(): void
    {
        $formula = $this->service->createFormulation('Nusantara Foods', 'Rendang Paste Premium', ['PEANUTS', 'SOY']);

        $this->assertSame('Rendang Paste Premium', $formula->product_name);
        $this->assertTrue((bool) $formula->is_immutable);
    }

    /**
     * (b) Allergen collision blocks patient/dietary meal order.
     */
    public function test_allergen_conflict_blocks_dietary_meal(): void
    {
        $formula = $this->service->createFormulation('HealthyLife', 'Peanut Butter Oatmeal', ['PEANUTS']);

        // 1. Patient allergic to PEANUTS -> BLOCKED
        $blocked = $this->service->orderDietaryMeal('HOSPITAL', 3001, $formula->formulation_code, ['PEANUTS']);
        $this->assertTrue((bool) $blocked->allergen_blocked);
        $this->assertSame('BLOCKED', $blocked->status);

        // 2. Patient with no peanut allergy -> APPROVED
        $clean = $this->service->orderDietaryMeal('HOSPITAL', 3002, $formula->formulation_code, ['SHELLFISH']);
        $this->assertFalse((bool) $clean->allergen_blocked);
        $this->assertSame('APPROVED', $clean->status);
    }

    /**
     * (c) Private label production ownership is segregated.
     */
    public function test_private_label_production(): void
    {
        $formula = $this->service->createFormulation('PrivateCo', 'Hotel Signature Tea', []);
        $pl = $this->service->orderPrivateLabel('HOTEL', $formula->formulation_code, 500.0, 15000000.0);

        $this->assertSame('HOTEL', $pl->client_entity_code);
        $this->assertTrue((bool) $pl->ownership_segregated);
        $this->assertEquals(500.00, (float) $pl->customer_owned_material_qty);
    }

    /**
     * (d) FEFO algorithm selects batch with earliest expiration date.
     */
    public function test_fefo_batch_selection(): void
    {
        $formula = $this->service->createFormulation('DairyCo', 'Pasteurized Milk', ['DAIRY']);

        // Batch 1 expires in 30 days
        $b1 = $this->service->addBatch($formula->formulation_code, 100, Carbon::now()->addDays(30));
        // Batch 2 expires in 10 days (Earlier!)
        $b2 = $this->service->addBatch($formula->formulation_code, 100, Carbon::now()->addDays(10));
        // Batch 3 expires in 60 days
        $b3 = $this->service->addBatch($formula->formulation_code, 100, Carbon::now()->addDays(60));

        $selected = $this->service->selectFefoBatch($formula->formulation_code);
        $this->assertNotNull($selected);
        $this->assertSame($b2->batch_code, $selected->batch_code);
    }

    /**
     * (e) Audit status healthy.
     */
    public function test_food_brand_nutrition_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
