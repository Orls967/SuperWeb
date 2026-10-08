<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_model_deployments', function (Blueprint $table) {
            $table->id();
            $table->string('model_code')->unique();
            $table->string('model_name');
            $table->string('model_version');
            $table->boolean('has_complete_documentation')->default(false); // 345.3 & 345.4
            $table->decimal('baseline_f1_score', 5, 4);
            $table->decimal('current_f1_score', 5, 4);
            $table->boolean('is_serving_traffic')->default(false);
            $table->string('rollback_version')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_model_retrain_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('evaluation_code')->unique();
            $table->string('model_code')->index();
            $table->string('candidate_version');
            $table->decimal('candidate_f1_score', 5, 4);
            $table->boolean('performance_degraded')->default(false); // 345.5 Edge case
            $table->boolean('release_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_model_retrain_evaluations');
        Schema::dropIfExists('analytics_model_deployments');
    }
};
