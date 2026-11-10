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
                if (! Schema::hasColumn('sim_runs', 'mode')) {
                    $table->string('mode', 16)->default('live')->after('status');
                }
                if (! Schema::hasColumn('sim_runs', 'current_day')) {
                    $table->integer('current_day')->default(0)->after('mode');
                }
                if (! Schema::hasColumn('sim_runs', 'total_days')) {
                    $table->integer('total_days')->default(1)->after('current_day');
                }
                if (! Schema::hasColumn('sim_runs', 'chain_hash')) {
                    $table->string('chain_hash', 64)->nullable()->after('total_days');
                }
                if (! Schema::hasColumn('sim_runs', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('chain_hash');
                }
                if (! Schema::hasColumn('sim_runs', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('created_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sim_runs')) {
            Schema::table('sim_runs', function (Blueprint $table) {
                $columns = ['completed_at', 'created_by', 'chain_hash', 'total_days', 'current_day', 'mode'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('sim_runs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
