<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_delegation_of_authority_matrix', function (Blueprint $table) {
            $table->id();
            $table->string('decision_type'); // CAPEX, CONTRACT, HIRING, PRICING, DISCLOSURE
            $table->string('authority_level'); // UNIT_HEAD, CEO, COMMITTEE, BOARD_OF_DIRECTORS
            $table->decimal('min_value', 15, 2)->default(0);
            $table->decimal('max_value', 15, 2);
            $table->timestamps();
            $table->unique(['decision_type', 'authority_level']);
        });

        Schema::create('gov_board_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('meeting_code')->unique();
            $table->string('committee_type'); // BOARD, AUDIT, RISK, SUSTAINABILITY, COMP
            $table->date('scheduled_date');
            $table->integer('total_eligible_members');
            $table->integer('attending_members_count');
            $table->integer('abstained_members_count')->default(0);
            $table->integer('active_voting_quorum');
            $table->boolean('quorum_achieved')->default(false);
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, QUORUM_MET, ADJOURNED_LACK_OF_QUORUM, CONCLUDED
            $table->string('adjournment_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('gov_conflict_of_interest_declarations', function (Blueprint $table) {
            $table->id();
            $table->string('declaration_code')->unique();
            $table->string('board_member_id')->index();
            $table->string('related_entity_or_vendor');
            $table->boolean('mandatory_abstain')->default(true);
            $table->string('alternate_member_id')->nullable(); // 231.7 edge case alternate
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('gov_decision_registers', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('decision_type');
            $table->decimal('decision_value', 15, 2)->default(0);
            $table->string('approved_by_level');
            $table->string('approved_by_entity');
            $table->string('meeting_code')->nullable()->index();
            $table->json('alternatives_evaluated')->nullable();
            $table->json('dissents_recorded')->nullable();
            $table->boolean('is_immutable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_decision_registers');
        Schema::dropIfExists('gov_conflict_of_interest_declarations');
        Schema::dropIfExists('gov_board_meetings');
        Schema::dropIfExists('gov_delegation_of_authority_matrix');
    }
};
