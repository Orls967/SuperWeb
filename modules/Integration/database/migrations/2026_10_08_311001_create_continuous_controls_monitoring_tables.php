<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_monitored_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->unique();
            $table->decimal('amount_usd', 18, 2);
            $table->string('counterparty_account');
            $table->boolean('is_suspicious_velocity_or_split')->default(false); // 311.1
            $table->boolean('step_up_auth_required')->default(false); // 311.2 High risk requires step-up auth
            $table->boolean('step_up_auth_completed')->default(false);
            $table->boolean('is_circuit_breaker_active')->default(false); // 311.5 Edge case
            $table->string('clearance_status')->default('CLEARED'); // CLEARED, HELD_FRAUD, CIRCUIT_BREAKER_APPEAL
            $table->timestamps();
        });

        Schema::create('finance_reconciliation_straight_throughs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();
            $table->integer('total_records');
            $table->integer('straight_through_matched_records'); // 311.3 Straight-Through-Processing (STP)
            $table->decimal('stp_rate_pct', 5, 2);
            $table->decimal('min_target_stp_pct', 5, 2)->default(95.00); // 311.3 & 311.4
            $table->boolean('stp_target_achieved')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_reconciliation_straight_throughs');
        Schema::dropIfExists('finance_monitored_transactions');
    }
};
