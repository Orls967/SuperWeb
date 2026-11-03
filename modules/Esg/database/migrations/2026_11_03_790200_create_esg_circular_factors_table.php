<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_circular_factors', function (Blueprint $table) {
            $table->id();
            $table->string('material_code', 32);
            $table->unsignedBigInteger('co2e_factor_g_per_kg'); // gram CO2e yang dihindari per kg material
            $table->string('version', 16);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->string('source_label', 64);
            $table->timestamps();

            $table->unique(['material_code', 'version']);
            $table->index(['material_code', 'effective_from'], 'idx_esg_circ_lookup');
        });

        Schema::create('esg_avoided_emissions', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('material_code', 32);
            $table->unsignedBigInteger('quantity_grams');
            $table->unsignedBigInteger('avoided_co2e_grams');
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_avoided_emissions');
        Schema::dropIfExists('esg_circular_factors');
    }
};

