<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 168.1: Commodity intake lots & grading
        Schema::create('food_intake_lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_code')->unique();
            $table->string('farmer_group_id');
            $table->string('commodity_name'); // PALM_FRUIT, COFFEE_BEANS, COCOA, GRAIN
            $table->decimal('raw_intake_kg', 18, 2);
            $table->decimal('grade_score', 5, 2);
            $table->decimal('settlement_price_per_kg', 18, 2);
            $table->decimal('total_settlement_idr', 18, 2);
            $table->string('status')->default('RECEIVED'); // RECEIVED, PROCESSING, QUARANTINED
            $table->timestamps();
        });

        // 168.3: Food processing orders & mass balance invariant
        Schema::create('food_processing_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code')->unique();
            $table->string('source_lot_code');
            $table->decimal('input_raw_kg', 18, 2);
            $table->decimal('output_finished_kg', 18, 2);
            $table->decimal('co_product_kg', 18, 2);
            $table->decimal('waste_kg', 18, 2);
            $table->decimal('mass_loss_pct', 5, 2)->default(0.00);
            $table->boolean('mass_balance_valid')->default(true);
            $table->timestamps();
        });

        // 168.4: Food safety QA & Quarantine holds
        Schema::create('food_qa_checks', function (Blueprint $table) {
            $table->id();
            $table->string('lot_code')->unique();
            $table->decimal('temperature_c', 5, 2);
            $table->decimal('moisture_pct', 5, 2);
            $table->boolean('lab_toxin_cleared')->default(true);
            $table->boolean('is_quarantined')->default(false);
            $table->boolean('is_released_for_shipping')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_qa_checks');
        Schema::dropIfExists('food_processing_runs');
        Schema::dropIfExists('food_intake_lots');
    }
};
