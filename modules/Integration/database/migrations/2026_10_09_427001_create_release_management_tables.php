<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_release_changes', function (Blueprint $table) {
            $table->id();
            $table->string('change_code')->unique();
            $table->string('title');
            $table->string('risk_class'); // standard, normal, emergency (427.1)
            $table->string('target_train'); // e.g. TRAIN-2026-W41
            $table->boolean('cab_approved')->default(false); // 427.1
            $table->boolean('emergency_retrospective_completed')->default(true); // 427.4, 427.5
            $table->string('status')->default('pending_approval'); // pending_approval, approved, deployed, rejected
            $table->timestamps();
        });

        Schema::create('plt_release_trains', function (Blueprint $table) {
            $table->id();
            $table->string('train_code')->unique();
            $table->date('departure_date');
            $table->integer('wip_feature_count')->default(0); // 427.6 WIP limit <= 10
            $table->boolean('dependency_freeze_cleared')->default(false); // 427.3, 427.4
            $table->decimal('lead_time_hours', 10, 2)->default(24.00); // 427.2 DORA metrics
            $table->decimal('change_failure_rate_percent', 5, 2)->default(0.00);
            $table->string('status')->default('boarding'); // boarding, frozen, released
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_release_trains');
        Schema::dropIfExists('plt_release_changes');
    }
};
