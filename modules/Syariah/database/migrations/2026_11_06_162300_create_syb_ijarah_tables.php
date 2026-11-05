<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_ijarah_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code', 64)->unique();
            $table->foreignId('customer_user_id')->constrained('users');
            $table->string('asset_name', 191);
            $table->unsignedBigInteger('asset_cost_idr');
            $table->unsignedSmallInteger('tenor_months');
            $table->unsignedBigInteger('total_ujrah_idr');
            $table->unsignedBigInteger('residual_option_price_idr')->default(0);
            $table->string('status', 32)->default('DRAFT');
            $table->foreignId('dps_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dps_reviewed_at')->nullable();
            $table->string('dps_opinion_note', 255)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('option_exercised_at')->nullable();
            $table->timestamp('ownership_transferred_at')->nullable();
            $table->unsignedBigInteger('total_ujrah_paid_idr')->default(0);
            $table->unsignedBigInteger('accumulated_depreciation_idr')->default(0);
            $table->unsignedBigInteger('carrying_book_value_idr');
            $table->unsignedBigInteger('total_tazir_idr')->default(0);
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();

            $table->index(['customer_user_id', 'status'], 'idx_syb_ijr_cust_status');
        });

        Schema::create('syb_ijarah_facility_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('syb_ijarah_facilities')->cascadeOnDelete();
            $table->unsignedSmallInteger('period');
            $table->date('due_date');
            $table->unsignedBigInteger('ujrah_idr');
            $table->unsignedBigInteger('depreciation_idr');
            $table->unsignedBigInteger('accumulated_depreciation_idr');
            $table->unsignedBigInteger('carrying_book_value_idr');
            $table->unsignedBigInteger('paid_amount_idr')->default(0);
            $table->string('status', 16)->default('UNPAID');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_idempotency_key', 120)->nullable()->unique();
            $table->timestamps();

            $table->unique(['facility_id', 'period'], 'uq_syb_ijr_sched_period');
            $table->index(['facility_id', 'status'], 'idx_syb_ijr_sched_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_ijarah_facility_schedules');
        Schema::dropIfExists('syb_ijarah_facilities');
    }
};
