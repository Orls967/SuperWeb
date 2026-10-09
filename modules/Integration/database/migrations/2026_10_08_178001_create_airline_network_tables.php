<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 178.1 & 178.2: Dynamic yield fare quotes with immutable floor guardrails
        Schema::create('avi_fare_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_code')->unique();
            $table->string('route_code'); // CGK-DPS
            $table->decimal('floor_price_idr', 18, 2);
            $table->decimal('quoted_fare_idr', 18, 2);
            $table->boolean('floor_price_respected')->default(true);
            $table->timestamps();
        });

        // 178.3: Airline loyalty miles & liability subledger (anti-duplicate codeshare earning)
        Schema::create('avi_loyalty_miles_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('earning_key')->unique(); // Anti-duplicate across codeshare partners
            $table->unsignedBigInteger('member_id');
            $table->string('flight_code');
            $table->integer('miles_earned');
            $table->decimal('liability_value_idr', 18, 2); // Rp 150 / mile
            $table->timestamps();
        });

        // 178.4: Irregular operations (disruptions, cancellations & statutory passenger compensation)
        Schema::create('avi_disruptions', function (Blueprint $table) {
            $table->id();
            $table->string('disruption_code')->unique();
            $table->string('flight_code');
            $table->string('reason'); // MECHANICAL, WEATHER
            $table->decimal('statutory_compensation_per_pax_idr', 18, 2)->default(300000.00); // Permenhub 89/2015
            $table->integer('impacted_pax_count');
            $table->decimal('total_compensation_pool_idr', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avi_disruptions');
        Schema::dropIfExists('avi_loyalty_miles_ledgers');
        Schema::dropIfExists('avi_fare_quotes');
    }
};
