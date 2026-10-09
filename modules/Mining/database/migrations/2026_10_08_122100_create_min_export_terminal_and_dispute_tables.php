<?php

declare(strict_types=1);

namespace Modules\Mining\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('min_export_terminal_stockpiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('terminal_code');
            $table->string('stockpile_code')->unique();
            $table->string('commodity');
            $table->double('opening_tonnage', 12, 3)->default(0.0);
            $table->double('loaded_tonnage', 12, 3)->default(0.0);
            $table->double('remaining_tonnage', 12, 3)->default(0.0);
            $table->timestamps();

            $table->index(['terminal_code', 'commodity']);
        });

        Schema::create('min_vessel_voyages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('voyage_number')->unique();
            $table->string('vessel_name');
            $table->string('buyer_party_id');
            $table->string('destination_country');
            $table->double('contracted_tonnage', 12, 3);
            $table->double('loaded_tonnage', 12, 3)->default(0.0);
            $table->string('bill_of_lading_hash')->nullable();
            $table->double('allowed_laytime_hours', 8, 2)->default(72.0);
            $table->double('actual_laytime_hours', 8, 2)->default(0.0);
            $table->bigInteger('demurrage_rate_per_hour_minor')->default(0);
            $table->bigInteger('demurrage_total_minor')->default(0);
            $table->boolean('sanctions_blocked')->default(false);
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, LOADING, DEPARTED, BLOCKED
            $table->timestamps();

            $table->index(['destination_country', 'status']);
        });

        Schema::create('min_cargo_quality_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dispute_number')->unique();
            $table->string('voyage_id');
            $table->string('surveyor_party_id');
            $table->double('seller_grade_pct', 5, 2);
            $table->double('buyer_grade_pct', 5, 2);
            $table->double('grade_tolerance_pct', 5, 2)->default(0.20);
            $table->string('sealed_sample_code')->nullable();
            $table->boolean('is_payment_held')->default(false);
            $table->string('status')->default('OPEN'); // OPEN, HELD, RESOLVED
            $table->timestamps();

            $table->index('voyage_id');
        });

        Schema::create('min_circular_fly_ash_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->string('cement_buyer_party_id');
            $table->double('fly_ash_tonnage', 12, 3);
            $table->bigInteger('price_per_ton_minor');
            $table->bigInteger('total_revenue_minor');
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index('cement_buyer_party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_circular_fly_ash_sales');
        Schema::dropIfExists('min_cargo_quality_disputes');
        Schema::dropIfExists('min_vessel_voyages');
        Schema::dropIfExists('min_export_terminal_stockpiles');
    }
};
