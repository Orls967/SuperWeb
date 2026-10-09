<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 53.1 & 53.5 Multi-Echelon Inventory Visibility & Classification
        Schema::create('sct_echelon_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 32);
            $table->string('item_name');
            $table->string('echelon_node', 32); // SUPPLIER, PORT, PLANT, DC, OUTLET
            $table->integer('on_hand_qty')->default(0);
            $table->integer('in_transit_qty')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->integer('safety_stock_qty')->default(0);
            $table->string('abc_class', 1)->default('A'); // A, B, C
            $table->string('xyz_class', 1)->default('X'); // X, Y, Z
            $table->timestamps();

            $table->unique(['item_code', 'echelon_node']);
        });

        // 53.2 Demand Forecasting Models & Accuracy Tracking
        Schema::create('sct_demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('forecast_code', 32)->unique();
            $table->string('item_code', 32);
            $table->string('period', 7); // YYYY-MM
            $table->string('algorithm_model', 32); // MOVING_AVG, EXP_SMOOTHING, HOLT_WINTERS
            $table->integer('forecast_qty');
            $table->integer('actual_qty')->nullable();
            $table->decimal('mape_percent', 5, 2)->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->string('override_reason')->nullable();
            $table->timestamps();
        });

        // 53.4 Available-To-Promise (ATP) & Capable-To-Promise (CTP)
        Schema::create('sct_order_promises', function (Blueprint $table) {
            $table->id();
            $table->string('promise_code', 32)->unique();
            $table->string('order_reference', 32);
            $table->string('item_code', 32);
            $table->integer('requested_qty');
            $table->integer('atp_confirmed_qty')->default(0);
            $table->integer('ctp_manufacturing_qty')->default(0);
            $table->date('promised_delivery_date');
            $table->string('promise_status', 20)->default('confirmed'); // confirmed, partial, rejected
            $table->timestamps();
        });

        // 53.6 Disruption Alerts & Impact Analysis
        Schema::create('sct_disruption_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_code', 32)->unique();
            $table->string('severity', 10); // CRITICAL, HIGH, MEDIUM, LOW
            $table->string('category', 32); // SUPPLIER_DELAY, MACHINE_BREAKDOWN, PORT_CONGESTION
            $table->string('title');
            $table->text('description');
            $table->integer('affected_orders_count')->default(0);
            $table->string('status', 20)->default('active'); // active, mitigated, resolved
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sct_disruption_alerts');
        Schema::dropIfExists('sct_order_promises');
        Schema::dropIfExists('sct_demand_forecasts');
        Schema::dropIfExists('sct_echelon_stocks');
    }
};
