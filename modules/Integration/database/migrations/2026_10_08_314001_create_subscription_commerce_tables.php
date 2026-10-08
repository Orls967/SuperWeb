<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_replenishment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->string('subscriber_id')->index();
            $table->string('sku');
            $table->integer('frequency_days')->default(30);
            $table->decimal('base_price_usd', 10, 2);
            $table->decimal('discount_ladder_pct', 5, 2); // e.g. 15% discount
            $table->decimal('final_discounted_price_usd', 10, 2);
            $table->string('status')->default('ACTIVE'); // ACTIVE, PAUSED, SKIPPED (314.2 & 314.5)
            $table->timestamps();
        });

        Schema::create('subscription_auto_ship_executions', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_code')->unique();
            $table->string('plan_code')->index();
            $table->date('scheduled_date');
            $table->boolean('inventory_available')->default(true);
            $table->boolean('payment_successful')->default(true);
            $table->string('execution_outcome'); // SHIPPED, PAUSED_OUT_OF_STOCK, FAILED_NO_SURPRISE_CHARGE (314.5)
            $table->boolean('surprise_charge_prevented')->default(true); // 314.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_auto_ship_executions');
        Schema::dropIfExists('subscription_replenishment_plans');
    }
};
