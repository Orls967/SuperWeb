<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hitl_copilot_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('suggestion_code')->unique();
            $table->string('role_code'); // DOCTOR, MECHANIC, DISPATCHER, CASHIER, AUDITOR
            $table->string('model_version');
            $table->text('ai_recommendation');
            $table->boolean('is_auto_executed')->default(false); // 270.4 strictly false!
            $table->string('human_decision')->default('PENDING'); // ACCEPTED, REJECTED, MODIFIED, PENDING
            $table->string('reviewer_user_id')->nullable(); // 270.7
            $table->text('reviewer_notes')->nullable();
            $table->boolean('is_honeypot_sample')->default(false); // 270.6
            $table->boolean('honeypot_passed')->default(true);
            $table->timestamps();
        });

        Schema::create('hitl_review_queues', function (Blueprint $table) {
            $table->id();
            $table->string('queue_code')->unique();
            $table->string('role_code')->index();
            $table->integer('pending_items_count')->default(0);
            $table->integer('sla_target_minutes')->default(15);
            $table->integer('sla_breached_count')->default(0);
            $table->decimal('disagreement_rate_pct', 5, 2)->default(0.00); // 270.2 & 270.5
            $table->boolean('model_review_required')->default(false); // 270.5 Edge case
            $table->timestamps();
        });

        Schema::create('hitl_skill_augmentations', function (Blueprint $table) {
            $table->id();
            $table->string('curriculum_code')->unique();
            $table->string('target_role');
            $table->string('disagreement_pattern');
            $table->text('training_module_notes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hitl_skill_augmentations');
        Schema::dropIfExists('hitl_review_queues');
        Schema::dropIfExists('hitl_copilot_suggestions');
    }
};
