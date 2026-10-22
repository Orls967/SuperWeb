<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_dr_failover_drills')) {
            Schema::create('core_dr_failover_drills', function (Blueprint $table): void {
                $table->id();
                $table->string('drill_code', 64)->unique();
                $table->string('scenario', 128);
                $table->string('source_region', 64);
                $table->string('target_region', 64);
                $table->integer('rpo_target_seconds')->default(0);
                $table->integer('rto_target_seconds')->default(900);
                $table->integer('rpo_achieved_seconds')->default(0);
                $table->integer('rto_achieved_seconds')->default(0);
                $table->bigInteger('data_loss_bytes')->default(0);
                $table->string('status', 32)->default('PENDING');
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('executed_at')->nullable();
                $table->timestamps();

                $table->index(['source_region', 'target_region', 'status']);
            });
        }

        if (! Schema::hasTable('core_dr_cluster_nodes')) {
            Schema::create('core_dr_cluster_nodes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('drill_id')->nullable()->constrained('core_dr_failover_drills')->cascadeOnDelete();
                $table->string('node_code', 64)->unique();
                $table->string('region', 64);
                $table->string('role', 48);
                $table->string('ip_address', 45);
                $table->integer('replication_lag_ms')->default(0);
                $table->string('sync_status', 32)->default('SYNCED');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['region', 'role', 'sync_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_dr_cluster_nodes');
        Schema::dropIfExists('core_dr_failover_drills');
    }
};
