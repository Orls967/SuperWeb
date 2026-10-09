<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 75.1 & 75.3 Demand and Waste Forecasting
        Schema::create('ven_demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('forecast_code', 32)->unique();
            $table->string('outlet_code', 32);
            $table->string('item_code', 32);
            $table->date('forecast_date');
            $table->string('algorithm_model', 32)->default('HOLT_WINTERS');
            $table->integer('footfall_factor')->default(100);
            $table->integer('forecast_qty');
            $table->integer('actual_qty')->nullable();
            $table->decimal('mape_percent', 5, 2)->nullable();
            $table->integer('waste_forecast_qty')->default(0);
            $table->integer('waste_actual_qty')->nullable();
            $table->decimal('waste_mape_percent', 5, 2)->nullable();
            $table->boolean('suggest_scaledown')->default(false);
            $table->timestamps();
        });

        // 75.2 Auto-PO Logs & Plafond
        Schema::create('ven_auto_pos', function (Blueprint $table) {
            $table->id();
            $table->string('auto_po_code', 32)->unique();
            $table->string('outlet_code', 32);
            $table->string('item_code', 32);
            $table->integer('qty_ordered');
            $table->bigInteger('estimated_total_idr');
            $table->bigInteger('daily_plafon_idr');
            $table->string('status', 32); // AUTO_APPROVED, MANUAL_APPROVAL_REQUIRED
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->timestamps();
        });

        // 75.4 Smart Vending Units
        Schema::create('ven_vending_units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code', 32)->unique();
            $table->string('location_name', 128);
            $table->string('status', 32)->default('ACTIVE'); // ACTIVE, MAINTENANCE, OFFLINE
            $table->integer('current_stock')->default(0);
            $table->integer('critical_threshold')->default(10);
            $table->integer('max_capacity')->default(100);
            $table->integer('total_sales_count')->default(0);
            $table->bigInteger('total_sales_idr')->default(0);
            $table->json('sensor_status')->nullable(); // coin_slot, optical_drop, door_lock
            $table->timestamps();
        });

        // 75.5 Vending Transactions (Sales & Restock)
        Schema::create('ven_vending_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_code', 32)->unique();
            $table->unsignedBigInteger('vending_unit_id');
            $table->string('item_code', 32);
            $table->integer('qty');
            $table->bigInteger('price_idr');
            $table->string('payment_method', 32); // QRIS, BIOMETRIC_FACE
            $table->string('biometric_token_hash', 64)->nullable();
            $table->string('status', 32)->default('COMPLETED');
            $table->timestamps();

            $table->foreign('vending_unit_id')->references('id')->on('ven_vending_units')->cascadeOnDelete();
        });

        // Restock tasks to prevent duplicate tasks
        Schema::create('ven_restock_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_code', 32)->unique();
            $table->unsignedBigInteger('vending_unit_id');
            $table->integer('suggested_qty');
            $table->string('status', 32)->default('SCHEDULED'); // SCHEDULED, PICKED, DELIVERED, COMPLETED
            $table->timestamps();

            $table->foreign('vending_unit_id')->references('id')->on('ven_vending_units')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_restock_tasks');
        Schema::dropIfExists('ven_vending_transactions');
        Schema::dropIfExists('ven_vending_units');
        Schema::dropIfExists('ven_auto_pos');
        Schema::dropIfExists('ven_demand_forecasts');
    }
};
