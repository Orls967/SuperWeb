<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 209.1: Continuous close subledger reconciliation with lock period controls
        Schema::create('fin_close_period_locks', function (Blueprint $table) {
            $table->id();
            $table->string('period_code')->unique(); // e.g. 2026-M10
            $table->decimal('general_ledger_balance_idr', 18, 2);
            $table->decimal('subledger_aggregate_idr', 18, 2);
            $table->decimal('reconciliation_variance_idr', 18, 2)->default(0.00);
            $table->boolean('is_period_locked')->default(false);
            $table->string('locked_by')->nullable();
            $table->timestamps();
        });

        // 209.3: Intercompany automated matching & elimination
        Schema::create('fin_intercompany_matchings', function (Blueprint $table) {
            $table->id();
            $table->string('match_code')->unique();
            $table->string('source_entity_code');
            $table->string('target_entity_code');
            $table->decimal('sender_invoice_amount_idr', 18, 2);
            $table->decimal('receiver_bill_amount_idr', 18, 2);
            $table->decimal('variance_idr', 18, 2)->default(0.00);
            $table->string('elimination_status')->default('BALANCED'); // BALANCED, MISMATCHED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_intercompany_matchings');
        Schema::dropIfExists('fin_close_period_locks');
    }
};
