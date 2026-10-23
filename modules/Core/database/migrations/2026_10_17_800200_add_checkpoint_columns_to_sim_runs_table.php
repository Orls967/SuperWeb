<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sim_runs')) {
            Schema::table('sim_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('sim_runs', 'last_completed_day')) {
                    $table->integer('last_completed_day')->default(0)->after('seed');
                }
                if (! Schema::hasColumn('sim_runs', 'last_state_hash')) {
                    $table->string('last_state_hash', 64)->nullable()->after('last_completed_day');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sim_runs')) {
            Schema::table('sim_runs', function (Blueprint $table) {
                if (Schema::hasColumn('sim_runs', 'last_state_hash')) {
                    $table->dropColumn('last_state_hash');
                }
                if (Schema::hasColumn('sim_runs', 'last_completed_day')) {
                    $table->dropColumn('last_completed_day');
                }
            });
        }
    }
};
