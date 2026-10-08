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
            $table->decimal('reported_entity_profit_idr', 18, 2);
            $table->decimal('aggregated_units_profit_idr', 18, 2);
            $table->decimal('hierarchy_discrepancy_idr', 18, 2)->default(0.00); // Must be 0
            $table->timestamps();
        });

        // 212.4: Margin bridge balancing (Volume + Mix + Price + Cost + FX == Net change)
        Schema::create('fin_margin_bridges', function (Blueprint $table) {
            $table->id();
            $table->string('bridge_code')->unique();
            $table->string('period_code');
            $table->decimal('volume_variance_idr', 18, 2);
            $table->decimal('mix_variance_idr', 18, 2);
            $table->decimal('price_variance_idr', 18, 2);
            $table->decimal('cost_variance_idr', 18, 2);
            $table->decimal('fx_variance_idr', 18, 2);
            $table->decimal('total_actual_margin_delta_idr', 18, 2);
            $table->decimal('unexplained_bridge_variance_idr', 18, 2)->default(0.00); // Sum == Delta
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_margin_bridges');
        Schema::dropIfExists('fin_profitability_hierarchies');
    }
};
