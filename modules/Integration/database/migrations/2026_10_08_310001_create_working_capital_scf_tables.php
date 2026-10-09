<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_capital_dynamic_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('discount_offer_code')->unique();
            $table->string('supplier_id')->index();
            $table->decimal('invoice_amount_usd', 15, 2);
            $table->decimal('annualized_yield_pct', 5, 2); // 310.2 Yield curve
            $table->decimal('discount_savings_usd', 15, 2);
            $table->string('allocation_status')->default('ALLOCATED'); // ALLOCATED, QUEUED_PRO_RATA (310.5)
            $table->timestamps();
        });

        Schema::create('working_capital_ar_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique();
            $table->integer('payment_history_months'); // 310.6 Data length
            $table->decimal('ar_credit_score', 5, 2); // 0 - 100
            $table->string('confidence_label')->default('HIGH'); // HIGH, LOW_CONSERVATIVE (310.6)
            $table->decimal('assigned_credit_limit_usd', 15, 2);
            $table->decimal('bad_debt_provision_pct', 5, 2); // 310.3 & 310.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_capital_ar_risk_scores');
        Schema::dropIfExists('working_capital_dynamic_discounts');
    }
};
