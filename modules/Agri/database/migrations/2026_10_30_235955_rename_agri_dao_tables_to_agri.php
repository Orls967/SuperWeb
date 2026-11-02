<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gov_votes') && ! Schema::hasTable('agri_votes')) {
            Schema::rename('gov_votes', 'agri_votes');
        }

        if (Schema::hasTable('gov_voter_weights') && ! Schema::hasTable('agri_voter_weights')) {
            Schema::rename('gov_voter_weights', 'agri_voter_weights');
        }

        if (Schema::hasTable('gov_proposals') && ! Schema::hasTable('agri_proposals')) {
            Schema::rename('gov_proposals', 'agri_proposals');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agri_proposals') && ! Schema::hasTable('gov_proposals')) {
            Schema::rename('agri_proposals', 'gov_proposals');
        }

        if (Schema::hasTable('agri_voter_weights') && ! Schema::hasTable('gov_voter_weights')) {
            Schema::rename('agri_voter_weights', 'gov_voter_weights');
        }

        if (Schema::hasTable('agri_votes') && ! Schema::hasTable('gov_votes')) {
            Schema::rename('agri_votes', 'gov_votes');
        }
    }
};
