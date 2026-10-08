<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_control_plane_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_code')->unique();
            $table->string('action_type'); // RESTART_WORKER, REPLAY_DLQ, FAILOVER
            $table->boolean('preconditions_satisfied')->default(false); // 364.1 & 364.4
            $table->boolean('dry_run_successful')->default(true);
            $table->boolean('kill_switch_active')->default(false); // 364.3 & 364.4
            $table->boolean('failed_mid_action')->default(false); // 364.5 Edge case
            $table->boolean('manual_takeover_engaged')->default(false);
            $table->boolean('execution_succeeded')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_control_plane_actions');
    }
};
