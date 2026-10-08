<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_dao_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_code')->unique();
            $table->string('proposal_class'); // STRATEGIC, OPERATIONAL, SOCIAL, TECHNICAL
            $table->string('title');
            $table->decimal('quorum_pct', 5, 2);
            $table->decimal('supermajority_threshold_pct', 5, 2);
            $table->boolean('is_binding')->default(true);
            $table->string('status')->default('ACTIVE'); // ACTIVE, CANCELLED, PASSED, REJECTED, EXECUTED
            $table->string('cancellation_reason')->nullable();
            $table->boolean('safety_review_cleared')->default(false);
            $table->timestamps();
        });

        Schema::create('gov_dao_vote_delegations', function (Blueprint $table) {
            $table->id();
            $table->string('delegator_golden_id')->index();
            $table->string('delegate_golden_id')->index();
            $table->string('proposal_class')->nullable();
            $table->decimal('voting_weight', 10, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gov_dao_votes_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('vote_code')->unique();
            $table->string('proposal_code')->index();
            $table->string('voter_golden_id')->index();
            $table->decimal('effective_weight', 10, 2);
            $table->string('vote_choice'); // YES, NO, ABSTAIN, SILENT_DELEGATED
            $table->string('prev_hash', 64);
            $table->string('vote_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_dao_votes_ledger');
        Schema::dropIfExists('gov_dao_vote_delegations');
        Schema::dropIfExists('gov_dao_proposals');
    }
};
