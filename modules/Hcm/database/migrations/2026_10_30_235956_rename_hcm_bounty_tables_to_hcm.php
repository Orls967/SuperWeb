<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gov_bounties') && ! Schema::hasTable('hcm_bounties')) {
            Schema::rename('gov_bounties', 'hcm_bounties');
        }

        if (Schema::hasTable('gov_bounty_claims') && ! Schema::hasTable('hcm_bounty_claims')) {
            Schema::rename('gov_bounty_claims', 'hcm_bounty_claims');
        }

        if (Schema::hasTable('gov_bounty_pofs') && ! Schema::hasTable('hcm_bounty_pofs')) {
            Schema::rename('gov_bounty_pofs', 'hcm_bounty_pofs');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hcm_bounty_pofs') && ! Schema::hasTable('gov_bounty_pofs')) {
            Schema::rename('hcm_bounty_pofs', 'gov_bounty_pofs');
        }

        if (Schema::hasTable('hcm_bounty_claims') && ! Schema::hasTable('gov_bounty_claims')) {
            Schema::rename('hcm_bounty_claims', 'gov_bounty_claims');
        }

        if (Schema::hasTable('hcm_bounties') && ! Schema::hasTable('gov_bounties')) {
            Schema::rename('hcm_bounties', 'gov_bounties');
        }
    }
};
