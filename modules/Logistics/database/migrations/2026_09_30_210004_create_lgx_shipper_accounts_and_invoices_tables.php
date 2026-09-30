<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_shipper_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipper_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('credit_limit_idr')->default(50_000_000);
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lgx_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32)->unique()->index();
            $table->foreignId('shipper_id')->constrained('users')->cascadeOnDelete();
            $table->string('billing_period', 16)->index(); // YYYY-MM
            $table->unsignedBigInteger('total_amount_idr');
            $table->unsignedBigInteger('paid_amount_idr')->default(0);
            $table->string('status', 16)->default('unpaid')->index(); // 'unpaid', 'paid', 'cancelled'
            $table->date('due_date')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_invoices');
        Schema::dropIfExists('lgx_shipper_accounts');
    }
};
