<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_benchmark_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('benchmark_code')->unique();
            $table->string('environment_fingerprint'); // 391.4 & 391.5 Edge case
            $table->decimal('measured_tps', 10, 2);
            $table->boolean('within_variance_band')->default(true);
            $table->boolean('suite_completeness_verified')->default(true); // 391.4 & 391.6 Risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_benchmark_profiles');
    }
};
