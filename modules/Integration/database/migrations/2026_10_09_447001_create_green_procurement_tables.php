<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_green_supplier_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique();
            $table->string('supplier_name');
            $table->decimal('sustainability_score', 5, 2); // 447.1
            $table->string('risk_tier'); // tier_1_green, tier_2_neutral, tier_3_lagging (447.1, 447.5)
            $table->decimal('green_discount_premium_percent', 5, 2)->default(0.00); // 447.3, 447.6
            $table->boolean('has_verified_footprint_data')->default(false); // 447.6 risk
            $table->boolean('decarbonization_clause_agreed')->default(false); // 447.2, 447.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_green_supplier_evaluations');
    }
};
