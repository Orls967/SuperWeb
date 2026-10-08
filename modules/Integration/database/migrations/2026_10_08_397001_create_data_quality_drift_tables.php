<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_data_quality_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_code')->unique();
            $table->decimal('data_value', 12, 4);
            $table->boolean('is_corrupt_or_anomalous')->default(false);
            $table->boolean('quarantined')->default(false); // 397.3 & 397.4
            $table->timestamps();
        });

        Schema::create('global_stress_price_feed_drifts', function (Blueprint $table) {
            $table->id();
            $table->string('feed_symbol')->unique();
            $table->decimal('baseline_price', 12, 4);
            $table->decimal('incoming_feed_price', 12, 4);
            $table->boolean('price_drift_detected')->default(false);
            $table->boolean('trading_frozen')->default(false); // 397.2 & 397.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_price_feed_drifts');
        Schema::dropIfExists('global_stress_data_quality_records');
    }
};
