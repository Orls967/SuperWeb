<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 180.1 & 180.4: Vessel registry & digital vessel passport
        Schema::create('flt_vessels', function (Blueprint $table) {
            $table->id();
            $table->string('imo_number')->unique();
            $table->string('vessel_name');
            $table->string('vessel_class'); // CONTAINER, BULKER, TANKER
            $table->decimal('deadweight_tonnage_dwt', 18, 2);
            $table->date('safety_certificate_expiry');
            $table->boolean('is_detained')->default(false);
            $table->string('passport_hash');
            $table->timestamps();
        });

        // 180.2: Ocean voyages & capacity cargo planning
        Schema::create('flt_voyages', function (Blueprint $table) {
            $table->id();
            $table->string('voyage_code')->unique();
            $table->string('imo_number');
            $table->string('origin_port');
            $table->string('destination_port');
            $table->decimal('planned_cargo_tons', 18, 2);
            $table->decimal('bunker_fuel_metric_tons', 18, 2);
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, DISPATCHED, COMPLETED
            $table->timestamps();
        });

        // 180.1 & 180.2: Charter party agreements & laytime settlements
        Schema::create('flt_charter_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('charter_code')->unique();
            $table->string('voyage_code');
            $table->decimal('daily_hire_rate_usd', 18, 2);
            $table->decimal('voyage_days', 6, 2);
            $table->decimal('demurrage_usd', 18, 2)->default(0.00);
            $table->decimal('total_charter_settlement_usd', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flt_charter_settlements');
        Schema::dropIfExists('flt_voyages');
        Schema::dropIfExists('flt_vessels');
    }
};
