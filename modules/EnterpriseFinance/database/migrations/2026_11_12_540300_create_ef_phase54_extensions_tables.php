<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ef_budget_encumbrances
        if (! Schema::hasTable('ef_budget_encumbrances')) {
            Schema::create('ef_budget_encumbrances', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('budget_id');
                $table->string('commitment_code', 64)->unique();
                $table->string('source_module', 32);
                $table->string('reference_number', 64);
                $table->unsignedBigInteger('amount_minor');
                $table->unsignedBigInteger('liquidated_amount_minor')->default(0);
                $table->string('status', 24)->default('RESERVED');
                $table->timestamp('reserved_at');
                $table->timestamp('liquidated_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->text('reason')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->foreign('budget_id')->references('id')->on('ef_budgets')->cascadeOnDelete();
                $table->index(['source_module', 'reference_number']);
            });
        }

        // 2. ef_financial_period_locks
        if (! Schema::hasTable('ef_financial_period_locks')) {
            Schema::create('ef_financial_period_locks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('entity_code', 32);
                $table->string('period', 7); // YYYY-MM
                $table->boolean('is_locked')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->string('closing_voucher_ref', 64)->nullable();
                $table->json('subledger_recon_summary')->nullable();
                $table->bigInteger('net_income_loss_minor')->default(0);
                $table->timestamps();

                $table->unique(['entity_code', 'period']);
            });
        }

        // 3. ef_sod_exceptions
        if (! Schema::hasTable('ef_sod_exceptions')) {
            Schema::create('ef_sod_exceptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('sod_rule_ref', 64);
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('granted_by');
                $table->text('justification');
                $table->timestamp('expires_at');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
            });
        }

        // 4. ef_control_points
        if (! Schema::hasTable('ef_control_points')) {
            Schema::create('ef_control_points', function (Blueprint $table) {
                $table->string('id', 32)->primary();
                $table->string('name', 128);
                $table->string('category', 32);
                $table->boolean('automated')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 5. ef_control_test_runs
        if (! Schema::hasTable('ef_control_test_runs')) {
            Schema::create('ef_control_test_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('control_id', 32);
                $table->timestamp('tested_at');
                $table->string('status', 24); // EFFECTIVE, DEFICIENT, FAILED
                $table->unsignedInteger('sample_count');
                $table->unsignedInteger('deviation_count')->default(0);
                $table->json('details')->nullable();
                $table->string('task_ref', 64)->nullable();
                $table->timestamps();

                $table->foreign('control_id')->references('id')->on('ef_control_points')->cascadeOnDelete();
            });
        }

        // 6. ef_tax_recon_summaries
        if (! Schema::hasTable('ef_tax_recon_summaries')) {
            Schema::create('ef_tax_recon_summaries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('entity_code', 32);
                $table->string('period', 7);
                $table->unsignedBigInteger('vat_output_minor')->default(0);
                $table->unsignedBigInteger('vat_input_minor')->default(0);
                $table->bigInteger('net_payable_minor')->default(0);
                $table->string('settlement_status', 24)->default('DRAFT');
                $table->string('spt_draft_number', 64)->nullable();
                $table->timestamps();

                $table->unique(['entity_code', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ef_tax_recon_summaries');
        Schema::dropIfExists('ef_control_test_runs');
        Schema::dropIfExists('ef_control_points');
        Schema::dropIfExists('ef_sod_exceptions');
        Schema::dropIfExists('ef_financial_period_locks');
        Schema::dropIfExists('ef_budget_encumbrances');
    }
};
