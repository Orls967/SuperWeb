<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_research_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('university_partner');
            $table->string('research_title');
            $table->text('ip_ownership_terms')->nullable(); // 265.1, 265.5
            $table->boolean('is_ip_agreement_signed')->default(false); // 265.5
            $table->string('status')->default('DRAFT_PENDING_IP'); // DRAFT_PENDING_IP, APPROVED_ACTIVE, COMPLETED
            $table->date('sandbox_expiry_date'); // 265.6
            $table->boolean('sandbox_expired')->default(false);
            $table->boolean('is_production_data_isolated')->default(true); // 265.6
            $table->timestamps();
        });

        Schema::create('academic_funding_tranches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->integer('tranche_number');
            $table->decimal('amount_usd', 15, 2);
            $table->string('milestone_deliverable');
            $table->boolean('is_released')->default(false);
            $table->timestamps();
        });

        Schema::create('academic_innovation_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('challenge_code')->unique();
            $table->string('problem_brief');
            $table->string('evaluator_1_id'); // 265.3
            $table->string('evaluator_2_id'); // 265.3
            $table->string('winner_submission_code')->nullable();
            $table->decimal('total_prize_usd', 15, 2);
            $table->boolean('implementation_verified')->default(false); // 265.7
            $table->boolean('payout_released')->default(false); // 265.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_innovation_challenges');
        Schema::dropIfExists('academic_funding_tranches');
        Schema::dropIfExists('academic_research_projects');
    }
};
