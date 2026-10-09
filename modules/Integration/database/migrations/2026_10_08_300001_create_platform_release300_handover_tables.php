<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_release300_handover_signoffs', function (Blueprint $table) {
            $table->id();
            $table->string('signoff_code')->unique();
            $table->string('release_tag')->default('v300-30-lines-complete');
            $table->boolean('all_30_lines_healthy')->default(false); // 300.4
            $table->boolean('all_hash_chains_valid')->default(false); // 300.3
            $table->decimal('total_ledger_variance_usd', 15, 2)->default(0.00); // 300.2 Σ=0
            $table->boolean('dr_failover_proven')->default(false); // 300.5
            $table->string('executive_architect_signoff_id')->nullable();
            $table->boolean('is_handover_complete')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_30lines_ledger_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('ledger_domain_line'); // e.g. CURRENCIES, TOKENS, CARBON, ZAKAT, WAKAF, RWA, POINTS, MILES
            $table->decimal('debit_sum_usd', 18, 2);
            $table->decimal('credit_sum_usd', 18, 2);
            $table->decimal('net_variance_usd', 18, 2); // 300.2 must be 0.00
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_30lines_ledger_reconciliations');
        Schema::dropIfExists('platform_release300_handover_signoffs');
    }
};
