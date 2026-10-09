<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_unified_close_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_code')->unique();
            $table->string('fiscal_period', 7); // YYYY-MM
            $table->decimal('ledger_net_income_usd', 15, 2);
            $table->decimal('statutory_net_income_usd', 15, 2);
            $table->decimal('management_net_income_usd', 15, 2);
            $table->decimal('reconciliation_variance_usd', 15, 2)->default(0.00);
            $table->text('reconciliation_explanation')->nullable(); // 252.5
            $table->integer('checklist_tasks_total')->default(0);
            $table->integer('checklist_tasks_completed')->default(0);
            $table->boolean('is_cycle_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('finance_close_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cycle_id');
            $table->string('task_name');
            $table->unsignedBigInteger('dependency_task_id')->nullable(); // 252.7
            $table->string('status')->default('PENDING'); // PENDING, IN_PROGRESS, COMPLETED, FAILED_ESCALATED
            $table->timestamps();
        });

        Schema::create('finance_shared_service_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sla_code')->unique();
            $table->string('service_stream'); // AP_PROCESSING, BILLING_OPERATIONS, CASH_APPLICATION, PAYROLL_OPS
            $table->integer('volume_processed');
            $table->decimal('target_sla_hours', 4, 1);
            $table->decimal('actual_avg_hours', 4, 1);
            $table->boolean('is_sla_breached')->default(false);
            $table->boolean('capacity_review_triggered')->default(false); // 252.6
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_shared_service_metrics');
        Schema::dropIfExists('finance_close_tasks');
        Schema::dropIfExists('finance_unified_close_cycles');
    }
};
