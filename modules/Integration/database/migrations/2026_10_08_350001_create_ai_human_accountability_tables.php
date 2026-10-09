<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_material_outcome_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('user_id');
            $table->string('outcome_category'); // PRICING, CREDIT, SCHEDULING
            $table->boolean('transparency_disclosure_provided')->default(false); // 350.3 & 350.4
            $table->boolean('has_appeal_path')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_decision_appeals', function (Blueprint $table) {
            $table->id();
            $table->string('appeal_code')->unique();
            $table->string('decision_code')->index();
            $table->string('assigned_human_reviewer_id')->nullable(); // 350.5 Edge case
            $table->decimal('response_turnaround_hours', 8, 2);
            $table->decimal('sla_hours', 8, 2)->default(48.00); // 350.5 SLA
            $table->boolean('sla_breached')->default(false);
            $table->string('status'); // PENDING, RESOLVED_HUMAN, REJECTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_decision_appeals');
        Schema::dropIfExists('ai_material_outcome_decisions');
    }
};
