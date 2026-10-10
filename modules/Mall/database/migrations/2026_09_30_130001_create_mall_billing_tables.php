<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. mall_tenant_sales_reports
        Schema::create('mall_tenant_sales_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lease_id')->constrained('mall_leases')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('mall_tenants')->cascadeOnDelete();
            $table->string('period_month', 7); // YYYY-MM
            $table->unsignedBigInteger('gross_sales')->default(0);
            $table->unsignedBigInteger('net_sales')->default(0);
            $table->integer('transaction_count')->default(0);
            $table->string('source', 30)->default('manual'); // manual, integrated, pos
            $table->timestamp('reported_at');
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'period_month']);
            $table->index(['tenant_id', 'period_month']);
        });

        // 2. mall_utility_tariffs
        Schema::create('mall_utility_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->string('utility_type', 30); // electricity, water
            $table->integer('tier_number')->default(1);
            $table->decimal('tier_min', 12, 2)->default(0.00);
            $table->decimal('tier_max', 12, 2)->nullable(); // null = unlimited
            $table->unsignedBigInteger('rate_per_unit');
            $table->unsignedBigInteger('standing_charge')->default(0);
            $table->date('effective_from');
            $table->timestamps();

            $table->index(['property_id', 'utility_type', 'effective_from'], 'mall_util_tariffs_prop_type_eff_idx');
        });

        // 3. mall_utility_readings
        Schema::create('mall_utility_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('mall_leases')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('mall_units')->cascadeOnDelete();
            $table->string('period_month', 7); // YYYY-MM
            $table->string('utility_type', 30); // electricity, water
            $table->decimal('previous_meter', 12, 2)->default(0.00);
            $table->decimal('current_meter', 12, 2);
            $table->decimal('usage', 12, 2);
            $table->unsignedBigInteger('amount');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['lease_id', 'period_month', 'utility_type']);
            $table->index(['unit_id', 'period_month']);
        });

        // 4. mall_overtime_requests
        Schema::create('mall_overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lease_id')->constrained('mall_leases')->cascadeOnDelete();
            $table->date('date');
            $table->string('start_time', 10);
            $table->string('end_time', 10);
            $table->decimal('hours', 5, 2);
            $table->unsignedBigInteger('rate_per_hour')->default(250000);
            $table->unsignedBigInteger('total_cost');
            $table->text('reason');
            $table->string('status', 30)->default('requested'); // requested, approved, rejected, billed
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->timestamps();

            $table->index(['lease_id', 'status', 'date']);
        });

        // 5. mall_invoices
        Schema::create('mall_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('lease_id')->constrained('mall_leases')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('mall_tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->string('period_month', 7); // YYYY-MM
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('penalty_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status', 30)->default('draft'); // draft, issued, partially_paid, paid, overdue, cancelled
            $table->date('due_date');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'period_month']);
            $table->index(['tenant_id', 'status', 'due_date']);
            $table->index(['property_id', 'period_month']);
        });

        // 6. mall_invoice_lines
        Schema::create('mall_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('mall_invoices')->cascadeOnDelete();
            $table->string('type', 30); // base_rent, revenue_share_topup, service_charge, electricity, water, ac_overtime, penalty
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status', 30)->default('unpaid'); // unpaid, partially_paid, paid
            $table->timestamps();

            $table->index(['invoice_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_invoice_lines');
        Schema::dropIfExists('mall_invoices');
        Schema::dropIfExists('mall_overtime_requests');
        Schema::dropIfExists('mall_utility_readings');
        Schema::dropIfExists('mall_utility_tariffs');
        Schema::dropIfExists('mall_tenant_sales_reports');
    }
};
