<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lifecycle_carbon_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('lca_assessment_code')->unique();
            $table->string('product_sku');
            $table->string('product_version'); // 328.1 & 328.4
            $table->decimal('lifecycle_emissions_kg_co2e', 10, 2);
            $table->string('precision_tier'); // PRECISE_VERIFIED, ESTIMATED_INCOMPLETE (328.5 Edge case)
            $table->boolean('eco_engineering_approved')->default(false); // 328.2 PLM ECO approval
            $table->timestamps();
        });

        Schema::create('product_circular_take_back_economics', function (Blueprint $table) {
            $table->id();
            $table->string('take_back_batch_code')->unique();
            $table->string('product_sku');
            $table->decimal('intake_mass_kg', 10, 2);
            $table->decimal('repaired_mass_kg', 10, 2)->default(0.00);
            $table->decimal('recycled_mass_kg', 10, 2)->default(0.00);
            $table->decimal('residual_waste_mass_kg', 10, 2)->default(0.00);
            $table->decimal('recovery_yield_pct', 5, 2); // 328.3 & 328.4 Reconciled
            $table->boolean('mass_balance_reconciled')->default(false); // 328.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_circular_take_back_economics');
        Schema::dropIfExists('product_lifecycle_carbon_assessments');
    }
};
