<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_cyber_ransomware_drills', function (Blueprint $table) {
            $table->id();
            $table->string('drill_code')->unique();
            $table->boolean('sandbox_isolated')->default(true); // 470.6 risk
            $table->boolean('containment_credentials_revoked')->default(false); // 470.1
            $table->decimal('target_rpo_minutes', 10, 2);
            $table->decimal('actual_rpo_minutes', 10, 2)->nullable();
            $table->decimal('target_rto_minutes', 10, 2);
            $table->decimal('actual_rto_minutes', 10, 2)->nullable();
            $table->decimal('ledger_reconcile_variance', 15, 2)->default(0.00); // 470.2 Σ=0
            $table->boolean('regulator_customer_notified')->default(false); // 470.3
            $table->boolean('war_room_escalated')->default(false); // 470.5 edge case
            $table->string('status')->default('contained'); // contained, restored_reconciled, failed_escalated
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_cyber_ransomware_drills');
    }
};
