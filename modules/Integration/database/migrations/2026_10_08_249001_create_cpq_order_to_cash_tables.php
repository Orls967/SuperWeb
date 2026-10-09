<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpq_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_code')->unique();
            $table->string('client_id')->index();
            $table->integer('version')->default(1);
            $table->decimal('total_quoted_amount', 15, 2);
            $table->json('configured_items_json');
            $table->string('status')->default('DRAFT'); // DRAFT, APPROVED, CONVERTED_TO_ORDER, EXPIRED
            $table->timestamp('expires_at'); // 249.2
            $table->timestamps();
        });

        Schema::create('cpq_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->unsignedBigInteger('quote_id');
            $table->string('client_id')->index();
            $table->decimal('order_amount', 15, 2);
            $table->boolean('credit_check_passed')->default(true);
            $table->string('credit_status')->default('APPROVED'); // APPROVED, DEFAULTED_BLOCKED (249.6)
            $table->string('status')->default('ACCEPTED'); // ACCEPTED, FULFILLED, INVOICED, PAID, ESCALATED_COLLECTION
            $table->string('delivery_evidence_doc')->nullable();
            $table->timestamps();
        });

        Schema::create('cpq_invoices_and_cash', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->unsignedBigInteger('order_id')->unique();
            $table->decimal('invoice_amount', 15, 2);
            $table->decimal('cash_collected_amount', 15, 2)->default(0.00);
            $table->boolean('is_fully_paid')->default(false);
            $table->timestamps();
        });

        Schema::create('cpq_revenue_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_code')->unique();
            $table->unsignedBigInteger('order_id')->unique();
            $table->decimal('total_contract_value', 15, 2);
            $table->string('recognition_pattern'); // POINT_IN_TIME, OVER_TIME_MONTHLY
            $table->decimal('deferred_revenue_amount', 15, 2)->default(0.00);
            $table->decimal('recognized_revenue_amount', 15, 2)->default(0.00);
            $table->integer('periods_count')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpq_revenue_schedules');
        Schema::dropIfExists('cpq_invoices_and_cash');
        Schema::dropIfExists('cpq_orders');
        Schema::dropIfExists('cpq_quotes');
    }
};
