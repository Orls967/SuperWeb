<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // K7: emisi spesifik tCO2e per ton produk, terverifikasi, berversi (label S2).
        Schema::create('esg_verified_product_emissions', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 32);
            $table->string('origin_country', 2);
            $table->decimal('t_co2e_per_t', 12, 6);
            $table->string('verification_ref', 64);
            $table->timestamp('verified_at');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->string('source_label', 64)->default('S2');
            $table->timestamps();

            $table->unique(['product_code', 'origin_country', 'effective_from'], 'uq_esg_vpe_scope');
            $table->index(['product_code', 'origin_country'], 'idx_esg_vpe_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_verified_product_emissions');
    }
};
