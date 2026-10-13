<?php

declare(strict_types=1);

namespace Modules\Egy\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egy_carbon_credit_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_code')->unique();
            $table->string('seller_entity_id');
            $table->string('buyer_entity_id');
            $table->string('vintage_year', 4);
            $table->double('carbon_credits_tons', 12, 3);
            $table->bigInteger('price_per_ton_minor');
            $table->bigInteger('total_value_minor');
            $table->string('status')->default('SETTLED'); // SETTLED, RETIRED, EXPIRED
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['seller_entity_id', 'buyer_entity_id']);
        });

        Schema::create('egy_renewable_energy_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('certificate_serial')->unique();
            $table->string('generation_asset_id');
            $table->string('owner_entity_id');
            $table->double('energy_mwh', 10, 2);
            $table->boolean('is_retired')->default(false);
            $table->string('retired_by_property_id')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['owner_entity_id', 'is_retired'], 'egy_rec_owner_retired_idx');
        });

        Schema::create('egy_cbam_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cbam_certificate_number')->unique();
            $table->string('exporter_entity_id');
            $table->string('container_id');
            $table->double('embedded_emissions_tons_co2', 10, 3);
            $table->bigInteger('cbam_price_per_ton_minor');
            $table->bigInteger('total_cbam_fee_minor');
            $table->string('billed_to_party_id');
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index('exporter_entity_id');
        });

        Schema::create('egy_green_lease_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('discount_code')->unique();
            $table->string('tenant_property_id');
            $table->integer('esg_score'); // 0-100
            $table->double('discount_rate_pct', 5, 2);
            $table->bigInteger('gross_utility_charge_minor');
            $table->bigInteger('green_discount_amount_minor');
            $table->bigInteger('net_utility_charge_minor');
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index('tenant_property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egy_green_lease_discounts');
        Schema::dropIfExists('egy_cbam_certificates');
        Schema::dropIfExists('egy_renewable_energy_certificates');
        Schema::dropIfExists('egy_carbon_credit_orders');
    }
};
