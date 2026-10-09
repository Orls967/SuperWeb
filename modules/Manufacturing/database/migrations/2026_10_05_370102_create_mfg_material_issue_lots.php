<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 37.2/37.9 Alokasi lot per issue — rekonsiliasi Σ issue per lot.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_material_issue_lots', function (Blueprint $table) {
            $table->id();
            $table->uuid('material_issue_id');
            $table->uuid('lot_id');
            $table->decimal('qty', 18, 6);
            $table->timestamps();

            $table->foreign('material_issue_id')->references('id')->on('mfg_material_issues')->cascadeOnDelete();
            $table->foreign('lot_id')->references('id')->on('mfg_material_lots')->cascadeOnDelete();
            $table->index(['lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_material_issue_lots');
    }
};
