<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_payroll_close_rehearsals', function (Blueprint $table) {
            $table->id();
            $table->string('rehearsal_batch_code')->unique();
            $table->string('period_month'); // e.g. 2026-11
            $table->integer('processed_headcount');
            $table->decimal('gross_payroll_usd', 18, 2);
            $table->decimal('prior_period_gross_usd', 18, 2);
            $table->decimal('variance_pct', 5, 2);
            $table->boolean('variance_approved')->default(true);
            $table->boolean('is_dry_run_passed')->default(true); // 322.3 & 322.5
            $table->boolean('live_run_permitted')->default(true); // 322.5 Edge case
            $table->timestamps();
        });

        Schema::create('global_payroll_payment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('exception_code')->unique();
            $table->string('rehearsal_batch_code')->index();
            $table->string('employee_id')->index();
            $table->decimal('failed_payment_amount_usd', 15, 2);
            $table->string('exception_reason'); // BANK_ACCOUNT_REJECTED, TAX_RULE_MISMATCH, DUPLICATE_ENTRY
            $table->string('accounting_treatment')->default('REMAINS_PAYABLE'); // 322.4 Must remain payable, not expensed
            $table->boolean('sla_escalation_triggered')->default(false); // 322.2 & 322.6 Risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_payroll_payment_exceptions');
        Schema::dropIfExists('global_payroll_close_rehearsals');
    }
};
