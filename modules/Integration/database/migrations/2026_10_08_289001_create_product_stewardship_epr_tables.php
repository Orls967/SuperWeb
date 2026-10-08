<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lifecycle_passports', function (Blueprint $table) {
            $table->id();
            $table->string('passport_code')->unique();
            $table->string('product_sku')->index();
            $table->decimal('carbon_footprint_kg_co2e', 10, 2);
            $table->decimal('repairability_score', 4, 2); // 0 to 10 (289.2 & 289.8 BOM-based)
            $table->boolean('spare_parts_available')->default(true); // 289.8
            $table->decimal('recyclability_pct', 5, 2);
            $table->string('public_qr_view_url');
            $table->timestamps();
        });

        Schema::create('epr_packaging_obligations', function (Blueprint $table) {
            $table->id();
            $table->string('obligation_code')->unique();
            $table->string('reporting_quarter'); // e.g. 2026-Q3
            $table->integer('units_sold');
            $table->decimal('packaging_weight_kg_per_unit', 8, 4);
            $table->decimal('total_plastic_obligation_kg', 12, 2); // 289.3 & 289.5
            $table->decimal('fee_per_kg_usd', 8, 4)->default(0.1500);
            $table->decimal('total_fee_liability_usd', 15, 2);
            $table->decimal('verified_collection_recycled_kg', 12, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('product_safety_recalls', function (Blueprint $table) {
            $table->id();
            $table->string('recall_code')->unique();
            $table->string('product_sku')->index();
            $table->string('defect_severity'); // CRITICAL, HIGH, MODERATE
            $table->integer('affected_units_count');
            $table->integer('contacted_units_count')->default(0);
            $table->integer('remedied_units_count')->default(0); // 289.4 (repair/replace/refund)
            $table->decimal('unreachable_liability_usd', 15, 2)->default(0.00); // 289.6
            $table->string('status')->default('NOTICE_ISSUED'); // NOTICE_ISSUED, IN_PROGRESS, CLOSED_RECONCILED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_safety_recalls');
        Schema::dropIfExists('epr_packaging_obligations');
        Schema::dropIfExists('product_lifecycle_passports');
    }
};
