<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 214.2 & 214.5: Condition monitoring, predictive health index & idempotent Work Order creation
        Schema::create('ops_asset_health_predictions', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('asset_class'); // MEDICAL_IMAGING, FACTORY_TURBINE, FLEET_VESSEL, AIRCRAFT_ENGINE
            $table->decimal('health_index_score', 5, 2); // 0 to 100
            $table->boolean('failure_predicted')->default(false);
            $table->string('generated_work_order_code')->nullable();
            $table->timestamps();
        });

        // 214.4: Spare parts criticality and availability gating for repairs
        Schema::create('ops_asset_spare_parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_code')->unique();
            $table->string('part_name');
            $table->string('criticality_tier'); // CRITICAL, ESSENTIAL, STANDARD
            $table->integer('stock_on_hand')->default(0);
            $table->integer('safety_stock_threshold')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_asset_spare_parts');
        Schema::dropIfExists('ops_asset_health_predictions');
    }
};
