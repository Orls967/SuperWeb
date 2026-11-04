<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // D19-2 Akad pembiayaan jual-beli Murabahah (biaya perolehan + margin tetap, tanpa bunga).
        Schema::create('syb_murabahah_financings', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code', 32)->unique();
            $table->unsignedBigInteger('customer_user_id');
            $table->unsignedBigInteger('supplier_party_ref');
            $table->string('asset_description', 191);
            $table->unsignedBigInteger('cost_price_idr');
            $table->unsignedBigInteger('margin_amount_idr');
            $table->unsignedBigInteger('total_selling_price_idr');
            $table->unsignedSmallInteger('tenor_months');
            $table->string('status', 24)->default('PROPOSED'); // PROPOSED, OFFICER_APPROVED, DPS_APPROVED, ASSET_PURCHASED, ACTIVE, SETTLED
            $table->unsignedBigInteger('proposed_by_user_id');
            $table->unsignedBigInteger('officer_approved_by_user_id')->nullable();
            $table->timestamp('officer_approved_at')->nullable();
            $table->unsignedBigInteger('dps_reviewer_user_id')->nullable();
            $table->timestamp('dps_reviewed_at')->nullable();
            $table->string('dps_opinion_note', 255)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedBigInteger('total_paid_idr')->default(0);
            $table->unsignedBigInteger('recognized_margin_idr')->default(0);
            $table->unsignedBigInteger('total_tazir_idr')->default(0);
            $table->string('contract_hash', 64)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['customer_user_id', 'status'], 'idx_syb_mrb_customer_status');

            $table->foreign('customer_user_id')->references('id')->on('users');
            $table->foreign('proposed_by_user_id')->references('id')->on('users');
            $table->foreign('officer_approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('dps_reviewer_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // D19-4 Jadwal angsuran murabahah tanpa bunga (LargestRemainder).
        Schema::create('syb_murabahah_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedSmallInteger('installment_no');
            $table->date('due_date');
            $table->unsignedBigInteger('principal_component_idr');
            $table->unsignedBigInteger('margin_component_idr');
            $table->unsignedBigInteger('installment_amount_idr');
            $table->unsignedBigInteger('paid_amount_idr')->default(0);
            $table->string('status', 16)->default('UNPAID'); // UNPAID, PAID
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->unique(['contract_id', 'installment_no'], 'uq_syb_mrb_sched_no');
            $table->index(['contract_id', 'status'], 'idx_syb_mrb_sched_status');

            $table->foreign('contract_id')->references('id')->on('syb_murabahah_financings')->cascadeOnDelete();
        });

        // D19-3 Denda keterlambatan (ta'zir) yang disalurkan ke dana kebajikan Qardhul Hasan, bukan pendapatan bank.
        Schema::create('syb_tazir_penalties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->unsignedBigInteger('customer_user_id');
            $table->unsignedBigInteger('amount_idr');
            $table->string('reason', 191);
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('syb_murabahah_financings')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('syb_murabahah_schedules')->nullOnDelete();
            $table->foreign('customer_user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_tazir_penalties');
        Schema::dropIfExists('syb_murabahah_schedules');
        Schema::dropIfExists('syb_murabahah_financings');
    }
};
