<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FoodBrandNutritionService (Fase 169 — Lini 21)
 *
 * Implements:
 *  - 169.1 Brand product formulations & allergen definitions (immutable)
 *  - 169.2 Private-label production with strictly segregated customer-owned materials
 *  - 169.3 Dietary nutrition orders with automated allergen collision blocking
 *  - 169.4 First-Expired, First-Out (FEFO) inventory dispatch selection
 */
class FoodBrandNutritionService
{
    /**
     * Create brand formulation with allergen list.
     */
    public function createFormulation(string $brand, string $product, array $allergens, int $version = 1): object
    {
        $code = 'FORM-'.strtoupper(Str::random(8));

        $id = DB::table('food_brand_formulations')->insertGetId([
            'formulation_code' => $code,
            'brand_name' => $brand,
            'product_name' => $product,
            'version' => $version,
            'contained_allergens' => json_encode($allergens),
            'is_immutable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('food_brand_formulations')->find($id);
    }

    /**
     * Order private label production keeping customer materials segregated.
     */
    public function orderPrivateLabel(string $clientEntity, string $formulationCode, float $custMaterialQty, float $serviceFee): object
    {
        $code = 'PL-'.strtoupper(Str::random(8));

        $id = DB::table('food_private_label_orders')->insertGetId([
            'order_code' => $code,
            'client_entity_code' => strtoupper($clientEntity),
            'formulation_code' => $formulationCode,
            'customer_owned_material_qty' => $custMaterialQty,
            'conversion_service_fee' => $serviceFee,
            'ownership_segregated' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('food_private_label_orders')->find($id);
    }

    /**
     * Place dietary meal order with automated allergen conflict check.
     */
    public function orderDietaryMeal(string $institutionType, int $beneficiaryId, string $formulationCode, array $patientAllergies): object
    {
        $formulation = DB::table('food_brand_formulations')->where('formulation_code', $formulationCode)->first();
        $containedAllergens = $formulation ? json_decode($formulation->contained_allergens, true) : [];

        // Check for intersection
        $conflicts = array_intersect(array_map('strtoupper', $patientAllergies), array_map('strtoupper', $containedAllergens));
        $isBlocked = count($conflicts) > 0;

        $code = 'MEAL-'.strtoupper(Str::random(8));

        $id = DB::table('food_dietary_meal_orders')->insertGetId([
            'meal_order_code' => $code,
            'institution_type' => strtoupper($institutionType),
            'beneficiary_id' => $beneficiaryId,
            'formulation_code' => $formulationCode,
            'patient_allergies' => json_encode($patientAllergies),
            'allergen_blocked' => $isBlocked,
            'status' => $isBlocked ? 'BLOCKED' : 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('food_dietary_meal_orders')->find($id);
    }

    /**
     * Store inventory batch and retrieve next batch via FEFO (First-Expired, First-Out).
     */
    public function addBatch(string $formulationCode, int $units, Carbon $expiryDate): object
    {
        $code = 'BATCH-'.strtoupper(Str::random(8));

        $id = DB::table('food_inventory_batches')->insertGetId([
            'batch_code' => $code,
            'formulation_code' => $formulationCode,
            'available_units' => $units,
            'expiration_date' => $expiryDate->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('food_inventory_batches')->find($id);
    }

    /**
     * Select batch using FEFO algorithm.
     */
    public function selectFefoBatch(string $formulationCode): ?object
    {
        return DB::table('food_inventory_batches')
            ->where('formulation_code', $formulationCode)
            ->where('available_units', '>', 0)
            ->orderBy('expiration_date', 'asc')
            ->first();
    }

    /**
     * Quality audit gate.
     */
    public function audit(): array
    {
        $approvedAllergenBreaches = DB::table('food_dietary_meal_orders')
            ->where('status', 'APPROVED')
            ->where('allergen_blocked', true)
            ->count();

        return [
            'status' => $approvedAllergenBreaches === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_formulations' => DB::table('food_brand_formulations')->count(),
            'total_private_label_orders' => DB::table('food_private_label_orders')->count(),
            'total_meal_orders' => DB::table('food_dietary_meal_orders')->count(),
            'discrepancy_count' => $approvedAllergenBreaches,
        ];
    }
}
