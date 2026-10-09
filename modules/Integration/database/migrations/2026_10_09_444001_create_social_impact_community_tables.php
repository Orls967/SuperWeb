<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_community_programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code')->unique();
            $table->string('community_name');
            $table->decimal('total_budget', 18, 2);
            $table->boolean('need_assessment_completed')->default(false); // 444.1
            $table->boolean('outcome_evaluation_passed')->default(false); // 444.3, 444.5
            $table->string('status')->default('designed'); // designed, active, paused_redesign, completed
            $table->timestamps();
        });

        Schema::create('esg_benefit_sharing_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_code')->unique();
            $table->foreignId('program_id')->constrained('esg_community_programs')->cascadeOnDelete();
            $table->decimal('formula_calculated_amount', 18, 2);
            $table->boolean('community_consent_approved')->default(true); // 444.2, 444.6
            $table->boolean('milestone_gate_verified')->default(false); // 444.4
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_benefit_sharing_payouts');
        Schema::dropIfExists('esg_community_programs');
    }
};
