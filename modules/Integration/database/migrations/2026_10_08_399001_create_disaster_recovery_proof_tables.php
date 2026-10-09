<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_dr_failover_drills', function (Blueprint $table) {
            $table->id();
            $table->string('drill_code')->unique();
            $table->integer('measured_rpo_seconds');
            $table->integer('target_rpo_seconds')->default(60);
            $table->integer('measured_rto_seconds');
            $table->integer('target_rto_seconds')->default(300);
            $table->boolean('is_blocker_finding')->default(false); // 399.4 & 399.5 Edge case
            $table->timestamps();
        });

        Schema::create('global_stress_dr_ledger_recoveries', function (Blueprint $table) {
            $table->id();
            $table->string('recovery_code')->unique();
            $table->integer('unexplained_discrepancy_count')->default(0);
            $table->boolean('hash_chain_verified')->default(true); // 399.2 & 399.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_dr_ledger_recoveries');
        Schema::dropIfExists('global_stress_dr_failover_drills');
    }
};
