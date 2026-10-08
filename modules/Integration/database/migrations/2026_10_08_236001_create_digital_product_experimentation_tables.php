<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppm_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('flag_key')->unique();
            $table->string('business_line')->index();
            $table->string('description');
            $table->boolean('is_enabled')->default(true);
            $table->integer('rollout_percentage')->default(0); // 0 to 100
            $table->boolean('is_killed')->default(false); // 236.2 real-time kill switch
            $table->string('kill_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('ppm_ab_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('experiment_code')->unique();
            $table->string('feature_flag_key')->index();
            $table->string('hypothesis');
            $table->string('metric_name');
            $table->decimal('control_conversion_rate', 5, 2)->default(0);
            $table->decimal('variant_conversion_rate', 5, 2)->default(0);
            $table->decimal('p_value', 5, 4)->nullable();
            $table->boolean('is_statistically_significant')->default(false);
            $table->string('status')->default('RUNNING'); // RUNNING, COMPLETED_ROLLOUT, COMPLETED_ROLLBACK, AUTO_KILLED_REGRESSION
            $table->string('auto_killed_reason')->nullable(); // 236.6 edge case
            $table->timestamps();
        });

        Schema::create('ppm_experiment_variants', function (Blueprint $table) {
            $table->id();
            $table->string('experiment_code')->index();
            $table->string('user_golden_id')->index();
            $table->string('assigned_variant'); // CONTROL, VARIANT_B
            $table->boolean('converted')->default(false);
            $table->timestamps();
            $table->unique(['experiment_code', 'user_golden_id']);
        });

        Schema::create('ppm_feature_telemetry', function (Blueprint $table) {
            $table->id();
            $table->string('feature_flag_key')->unique();
            $table->integer('monthly_active_users')->default(0);
            $table->decimal('dropoff_rate_pct', 5, 2)->default(0);
            $table->boolean('is_sunset_candidate')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppm_feature_telemetry');
        Schema::dropIfExists('ppm_experiment_variants');
        Schema::dropIfExists('ppm_ab_experiments');
        Schema::dropIfExists('ppm_feature_flags');
    }
};
