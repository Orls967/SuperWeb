<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sim_event_spine')) {
            Schema::table('sim_event_spine', function (Blueprint $table): void {
                $table->index(['topic', 'event_name', 'occurred_at'], 'sim_spine_tpc_evt_occ_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sim_event_spine')) {
            Schema::table('sim_event_spine', function (Blueprint $table): void {
                $table->dropIndex('sim_spine_tpc_evt_occ_idx');
            });
        }
    }
};
