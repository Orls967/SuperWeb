<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_gifts_hospitality_registries', function (Blueprint $table) {
            $table->id();
            $table->string('gift_code')->unique();
            $table->string('employee_id');
            $table->string('counterparty_name');
            $table->decimal('value_usd', 10, 2);
            $table->decimal('policy_threshold_usd', 10, 2)->default(100.00); // 340.2 & 340.4
            $table->boolean('pre_approved_by_compliance')->default(false);
            $table->boolean('gift_accepted_or_given')->default(false);
            $table->timestamps();
        });

        Schema::create('commercial_intermediary_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_code')->unique();
            $table->string('agent_id');
            $table->decimal('fee_amount_usd', 15, 2);
            $table->boolean('has_documented_economic_rationale')->default(false); // 340.3, 340.4, 340.6
            $table->boolean('bribery_collusion_flagged')->default(false); // 340.5 Edge case
            $table->boolean('independent_investigation_ordered')->default(false);
            $table->boolean('payment_cleared')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_intermediary_payments');
        Schema::dropIfExists('corporate_gifts_hospitality_registries');
    }
};
