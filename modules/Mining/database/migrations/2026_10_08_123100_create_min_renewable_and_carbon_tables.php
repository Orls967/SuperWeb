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
        Schema::create('min_renewable_energy_billings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('billing_number')->unique();
            $table->string('producer_site_id');
            $table->string('consumer_entity_id');
            $table->double('energy_kwh_delivered', 12, 2);
            $table->bigInteger('rate_per_kwh_minor'); // IDR per kWh
            $table->bigInteger('total_amount_minor');
            $table->double('scope1_avoided_tons_co2', 10, 4);
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['producer_site_id', 'consumer_entity_id']);
        });

        Schema::create('min_carbon_credit_issuances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_code')->unique();
            $table->string('site_id');
            $table->string('project_type'); // ARR_REFORESTATION, METHANE_CAPTURE
            $table->double('verified_ndvi_score', 4, 3); // 0.000 to 1.000
            $table->double('verified_carbon_tons', 12, 3);
            $table->double('issued_credits_tons', 12, 3);
            $table->string('registry_serial_number')->unique();
            $table->string('status')->default('ISSUED'); // ISSUED, RETIRED, TRADED
            $table->timestamps();

            $table->index('site_id');
        });

        Schema::create('min_green_mineral_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->unique();
            $table->string('buyer_party_id');
            $table->string('commodity');
            $table->double('tonnage', 12, 3);
            $table->string('dpp_passport_hash'); // Digital Product Passport hash
            $table->bigInteger('base_price_minor');
            $table->bigInteger('green_premium_adder_minor');
            $table->bigInteger('total_settled_minor');
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index('buyer_party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_green_mineral_sales');
        Schema::dropIfExists('min_carbon_credit_issuances');
        Schema::dropIfExists('min_renewable_energy_billings');
    }
};
