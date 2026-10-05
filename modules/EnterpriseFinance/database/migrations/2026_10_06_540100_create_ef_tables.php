<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 54.1 Enterprise Budgeting & Encumbrance
        Schema::create('ef_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('budget_code', 32)->unique();
            $table->string('fiscal_year', 4); // 2026
            $table->string('cost_center_code', 32);
            $table->string('account_code', 32);
            $table->bigInteger('allocated_amount_idr');
            $table->bigInteger('encumbered_amount_idr')->default(0);
            $table->bigInteger('spent_amount_idr')->default(0);
            $table->string('control_type', 10)->default('HARD_STOP'); // HARD_STOP, SOFT_STOP
            $table->timestamps();

            $table->unique(['fiscal_year', 'cost_center_code', 'account_code']);
        });

        // 54.3 Simulator Kepatuhan Perpajakan Nasional (PPN & PPh)
        Schema::create('ef_tax_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7); // YYYY-MM
            $table->string('tax_type', 20); // PPN, PPH_21, PPH_22, PPH_23, PPH_4_2
            $table->bigInteger('tax_base_idr');
            $table->bigInteger('input_tax_idr')->default(0); // Pajak Masukan (PPN)
            $table->bigInteger('output_tax_idr')->default(0); // Pajak Keluaran (PPN)
            $table->bigInteger('withheld_tax_idr')->default(0); // PPh dipotong
            $table->bigInteger('payable_or_refundable_idr')->default(0);
            $table->string('status', 20)->default('draft'); // draft, reported
            $table->timestamps();

            $table->unique(['period', 'tax_type']);
        });

        // 54.4 Segregation of Duties (SoD) Matrix
        Schema::create('ef_sod_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code', 32)->unique();
            $table->string('role_a', 32);
            $table->string('role_b', 32);
            $table->string('description');
            $table->string('risk_level', 10)->default('CRITICAL'); // CRITICAL, HIGH, MEDIUM
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 54.6 Kalender Kepatuhan Regulasi
        Schema::create('ef_compliance_deadlines', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 32)->unique();
            $table->string('title');
            $table->string('regulatory_body'); // DJP, BPOM, OJK, KEMENAKER
            $table->date('due_date');
            $table->string('assigned_role', 32)->default('auditor');
            $table->string('status', 20)->default('pending'); // pending, fulfilled, overdue
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ef_compliance_deadlines');
        Schema::dropIfExists('ef_sod_rules');
        Schema::dropIfExists('ef_tax_summaries');
        Schema::dropIfExists('ef_budgets');
    }
};
