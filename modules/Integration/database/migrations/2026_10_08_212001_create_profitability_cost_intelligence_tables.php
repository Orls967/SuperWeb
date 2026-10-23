<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 212.1 & 212.5: Profitability hierarchy records (Unit aggregate == Entity total)
        Schema::create('fin_profitability_hierarchies', function (Blueprint $table) {
            $table->id();
            $table->string('hierarchy_code')->unique();
            $table->string('entity_code');
            $table->bigInteger('reported_entity_profit_idr');
            $table->bigInteger('aggregated_units_profit_idr');
            $table->bigInteger('hierarchy_discrepancy_idr')->default(0); // Must be 0
            $table->timestamps();
        });

        // 212.4: Margin bridge balancing (Volume + Mix + Price + Cost + FX == Net change)
        Schema::create('fin_margin_bridges', function (Blueprint $table) {
            $table->id();
            $table->string('bridge_code')->unique();
            $table->string('period_code');
            $table->bigInteger('volume_variance_idr');
            $table->bigInteger('mix_variance_idr');
            $table->bigInteger('price_variance_idr');
            $table->bigInteger('cost_variance_idr');
            $table->bigInteger('fx_variance_idr');
            $table->bigInteger('total_actual_margin_delta_idr');
            $table->bigInteger('unexplained_bridge_variance_idr')->default(0); // Sum == Delta
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_margin_bridges');
        Schema::dropIfExists('fin_profitability_hierarchies');
    }
};
