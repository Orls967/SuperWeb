<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_service_oncall_registries', function (Blueprint $table) {
            $table->id();
            $table->string('service_code')->unique();
            $table->string('service_name');
            $table->string('criticality_tier'); // CRITICAL, NON_CRITICAL
            $table->string('named_owner')->nullable(); // 363.1 & 363.4
            $table->string('oncall_rotation_id')->nullable(); // 363.1, 363.4, 363.5 Edge case
            $table->boolean('readiness_passed')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_slo_error_budget_releases', function (Blueprint $table) {
            $table->id();
            $table->string('release_code')->unique();
            $table->string('service_code')->index();
            $table->decimal('remaining_error_budget_pct', 5, 2);
            $table->boolean('budget_exhausted')->default(false); // 363.2 & 363.4
            $table->boolean('accountable_exception_approved')->default(false);
            $table->boolean('release_frozen')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_slo_error_budget_releases');
        Schema::dropIfExists('platform_service_oncall_registries');
    }
};
