<?php

declare(strict_types=1);

namespace Modules\Ret\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 139.1 Dark store & quick commerce orders
        Schema::create('ret_quick_commerce_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_code')->unique();
            $table->string('dark_store_id');
            $table->string('customer_id');
            $table->timestamp('placed_at');
            $table->timestamp('promised_delivery_at'); // 30-min promise
            $table->timestamp('actual_delivered_at')->nullable();
            $table->integer('picking_duration_seconds')->default(0);
            $table->boolean('is_sla_breached')->default(false);
            $table->bigInteger('auto_credit_compensation_minor')->default(0);
            $table->boolean('compensation_issued')->default(false);
            $table->string('status')->default('DELIVERED');
            $table->timestamps();

            $table->index(['dark_store_id', 'status']);
        });

        // 139.3 Crowdshipping driver tasks
        Schema::create('ret_crowdshipping_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_code')->unique();
            $table->string('order_code');
            $table->string('crowd_driver_id');
            $table->bigInteger('order_value_minor');
            $table->bigInteger('delivery_fee_minor');
            $table->string('pod_hash')->nullable();
            $table->string('status')->default('COMPLETED');
            $table->timestamps();

            $table->index(['crowd_driver_id', 'status']);
        });

        // 139.4 Reusable Packaging Deposits & Reverse Loop
        Schema::create('ret_packaging_deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('deposit_code')->unique();
            $table->string('customer_id');
            $table->string('packaging_type'); // TOTE_BAG, INSULATED_BOX, GLASS_CONTAINER
            $table->bigInteger('deposit_amount_minor'); // e.g. 50,000 IDR
            $table->string('status')->default('CIRCULATING'); // CIRCULATING, RETURNED_REFUNDED, FORFEITED
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ret_packaging_deposits');
        Schema::dropIfExists('ret_crowdshipping_tasks');
        Schema::dropIfExists('ret_quick_commerce_orders');
    }
};
