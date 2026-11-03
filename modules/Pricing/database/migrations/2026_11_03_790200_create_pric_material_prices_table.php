<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pric_material_prices', function (Blueprint $table) {
            $table->id();
            $table->string('material_code', 32);
            $table->string('grade_code', 16);
            $table->unsignedBigInteger('price_per_kg_idr');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['material_code', 'grade_code', 'is_active', 'effective_from'], 'idx_pric_mat_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pric_material_prices');
    }
};

