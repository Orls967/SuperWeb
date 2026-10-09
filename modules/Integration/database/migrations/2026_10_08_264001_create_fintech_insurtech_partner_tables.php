<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fintech_partner_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_code')->unique();
            $table->string('country_code', 2);
            $table->string('partner_name');
            $table->decimal('cost_rate_pct', 4, 2);
            $table->decimal('success_rate_pct', 5, 2);
            $table->boolean('is_healthy')->default(true);
            $table->integer('priority_order')->default(1);
            $table->timestamps();
        });

        Schema::create('fintech_routed_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_code')->unique();
            $table->decimal('amount_usd', 15, 2);
            $table->string('primary_partner');
            $table->string('actual_routed_partner');
            $table->boolean('failover_occurred')->default(false); // 264.4
            $table->boolean('is_degraded_held')->default(false); // 264.5
            $table->string('status')->default('SETTLED'); // SETTLED, HELD_DEGRADED, FAILED
            $table->timestamps();
        });

        Schema::create('fintech_settlement_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_code')->unique();
            $table->string('partner_name')->index();
            $table->date('settlement_date');
            $table->decimal('internal_ledger_amount_usd', 15, 2);
            $table->decimal('partner_statement_amount_usd', 15, 2);
            $table->decimal('variance_amount_usd', 15, 2)->default(0.00);
            $table->boolean('is_exception')->default(false); // 264.7
            $table->integer('exception_aging_days')->default(0); // 264.7
            $table->timestamps();
        });

        Schema::create('fintech_open_finance_consents', function (Blueprint $table) {
            $table->id();
            $table->string('consent_token')->unique();
            $table->string('user_id')->index();
            $table->string('partner_name');
            $table->string('scope_permitted');
            $table->boolean('is_active')->default(true); // 264.3, 264.4
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fintech_open_finance_consents');
        Schema::dropIfExists('fintech_settlement_reconciliations');
        Schema::dropIfExists('fintech_routed_transactions');
        Schema::dropIfExists('fintech_partner_routes');
    }
};
