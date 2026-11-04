<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename tables back to official gov_ prefix if needed
        if (Schema::hasTable('agri_proposals') && ! Schema::hasTable('gov_proposals')) {
            Schema::rename('agri_proposals', 'gov_proposals');
        }
        if (Schema::hasTable('agri_voter_weights') && ! Schema::hasTable('gov_voter_weights')) {
            Schema::rename('agri_voter_weights', 'gov_voter_weights');
        }
        if (Schema::hasTable('agri_votes') && ! Schema::hasTable('gov_votes')) {
            Schema::rename('agri_votes', 'gov_votes');
        }

        // 2. Add Merkle tree and voting window columns to gov_proposals
        if (Schema::hasTable('gov_proposals')) {
            Schema::table('gov_proposals', function (Blueprint $table) {
                if (! Schema::hasColumn('gov_proposals', 'merkle_root')) {
                    $table->string('merkle_root', 64)->nullable()->after('quorum_weight_required');
                }
                if (! Schema::hasColumn('gov_proposals', 'voting_starts_at')) {
                    $table->timestamp('voting_starts_at')->nullable()->after('merkle_root');
                }
                if (! Schema::hasColumn('gov_proposals', 'voting_ends_at')) {
                    $table->timestamp('voting_ends_at')->nullable()->after('voting_starts_at');
                }
                if (! Schema::hasColumn('gov_proposals', 'epc_project_id')) {
                    $table->string('epc_project_id', 36)->nullable()->after('execution_reference_code');
                }
            });
        }

        // 3. Add Merkle proof column to gov_votes
        if (Schema::hasTable('gov_votes')) {
            Schema::table('gov_votes', function (Blueprint $table) {
                if (! Schema::hasColumn('gov_votes', 'merkle_proof')) {
                    $table->json('merkle_proof')->nullable()->after('vote_receipt_hash');
                }
            });
        }
    }

    public function down(): void {}
};
