<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_data_model_deployments', function (Blueprint $table) {
            $table->id();
            $table->string('change_code')->unique();
            $table->string('model_or_dataset_name');
            $table->string('version');
            $table->string('prior_version')->nullable();
            $table->text('rollback_plan_details'); // 432.1, 432.5 rollback mandatory
            $table->text('lineage_impact_summary'); // 432.2, 432.6 lineage impact
            $table->boolean('lineage_gate_passed')->default(false);
            $table->decimal('drift_score', 5, 2)->default(0.00); // 432.3
            $table->boolean('drift_alert_triggered')->default(false);
            $table->string('status')->default('pending_approval'); // pending_approval, deployed, rolled_back
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_data_model_deployments');
    }
};
