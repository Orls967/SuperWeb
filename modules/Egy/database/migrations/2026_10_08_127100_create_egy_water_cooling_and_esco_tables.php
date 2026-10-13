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
        Schema::create('egy_water_distributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('distribution_code')->unique();
            $table->string('consumer_property_id');
            $table->double('input_volume_m3', 12, 2);
            $table->double('leakage_loss_m3', 12, 2)->default(0.0);
            $table->double('delivered_volume_m3', 12, 2);
            $table->bigInteger('water_tariff_minor');
            $table->bigInteger('water_charge_minor');
            $table->timestamps();

            $table->index('consumer_property_id');
        });

        Schema::create('egy_district_cooling_meters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('meter_code')->unique();
            $table->string('building_property_id');
            $table->double('thermal_kwh_consumed', 12, 2);
            $table->double('cop_efficiency_factor', 4, 2)->default(4.5);
            $table->bigInteger('rate_per_thermal_kwh_minor');
            $table->bigInteger('total_charge_minor');
            $table->timestamps();

            $table->index('building_property_id');
        });

        Schema::create('egy_utility_consolidated_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->string('property_id');
            $table->string('billing_period'); // YYYY-MM
            $table->bigInteger('electricity_charge_minor')->default(0);
            $table->bigInteger('water_charge_minor')->default(0);
            $table->bigInteger('district_cooling_charge_minor')->default(0);
            $table->bigInteger('gas_charge_minor')->default(0);
            $table->bigInteger('total_consolidated_minor');
            $table->string('status')->default('ISSUED'); // ISSUED, PAID, OVERDUE
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'billing_period'], 'egy_util_inv_prop_period_idx');
        });

        Schema::create('egy_esco_performance_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->unique();
            $table->string('client_property_id');
            $table->bigInteger('baseline_energy_cost_minor');
            $table->bigInteger('actual_energy_cost_minor');
            $table->bigInteger('verified_savings_minor');
            $table->double('esco_share_pct', 5, 2)->default(60.0);
            $table->bigInteger('esco_remuneration_minor');
            $table->bigInteger('client_retained_saving_minor');
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index('client_property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egy_esco_performance_contracts');
        Schema::dropIfExists('egy_utility_consolidated_invoices');
        Schema::dropIfExists('egy_district_cooling_meters');
        Schema::dropIfExists('egy_water_distributions');
    }
};
