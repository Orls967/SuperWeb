<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_restructuring_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_ref', 64)->unique();
            $table->string('financing_ref', 64);
            $table->string('customer_ref', 64);
            $table->string('contract_type', 32);
            $table->bigInteger('original_principal_minor')->default(0);
            $table->bigInteger('original_margin_minor')->default(0);
            $table->unsignedInteger('margin_haircut_bps')->default(0);
            $table->bigInteger('revised_principal_minor')->default(0);
            $table->bigInteger('revised_margin_minor')->default(0);
            $table->bigInteger('total_restructured_debt_minor')->default(0);
            $table->unsignedInteger('original_tenor_months')->default(12);
            $table->unsignedInteger('new_tenor_months')->default(24);
            $table->unsignedInteger('grace_period_months')->default(0);
            $table->string('status', 32)->default('REQUESTED');
            $table->text('reason')->nullable();
            $table->string('requested_by', 64);
            $table->string('dps_reviewer_ref', 64)->nullable();
            $table->text('dps_notes')->nullable();
            $table->string('certificate_hash', 64);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['financing_ref', 'status']);
            $table->index('customer_ref');
        });

        Schema::create('syb_restructured_installments', function (Blueprint $table) {
            $table->id();
            $table->string('request_ref', 64);
            $table->unsignedInteger('month_number');
            $table->boolean('is_grace')->default(false);
            $table->bigInteger('principal_minor')->default(0);
            $table->bigInteger('margin_minor')->default(0);
            $table->bigInteger('total_installment_minor')->default(0);
            $table->bigInteger('remaining_principal_minor')->default(0);
            $table->bigInteger('remaining_margin_minor')->default(0);
            $table->date('due_date');
            $table->string('payment_status', 32)->default('PENDING');
            $table->timestamp('paid_at')->nullable();
            $table->string('ledger_journal_ref', 64)->nullable();
            $table->timestamps();

            $table->index(['request_ref', 'month_number']);
            $table->index(['payment_status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_restructured_installments');
        Schema::dropIfExists('syb_restructuring_requests');
    }
};
