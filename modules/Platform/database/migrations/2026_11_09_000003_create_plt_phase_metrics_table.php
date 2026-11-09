<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_phase_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('phase_id')->unique();
            $table->integer('b1_loc')->default(0);
            $table->integer('b2_loc')->default(0);
            $table->integer('b3_loc')->default(0);
            $table->decimal('decision_density', 8, 4)->default(0.0000);
            $table->decimal('duplicate_ratio', 8, 4)->default(0.0000);
            $table->integer('orphan_count')->default(0);
            $table->string('status')->default('compliant');
            $table->string('certificate_hash')->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->index(['phase_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_phase_metrics');
    }
};

