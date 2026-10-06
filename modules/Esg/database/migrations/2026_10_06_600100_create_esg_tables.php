<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_emissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('emission_number')->unique();
            $table->string('entity_id');
            $table->string('scope'); // scope_1, scope_2, scope_3
            $table->string('activity_type'); // fuel_combustion, electricity_grid, freight_transport, air_travel
            $table->decimal('activity_data_amount', 14, 4); // liters, kWh, ton-km
            $table->string('activity_uom', 20);
            $table->decimal('emission_factor', 10, 6); // kg CO2e per unit
            $table->decimal('co2e_kg', 14, 4);
            $table->date('reporting_period');
            $table->string('source_module', 50)->nullable();
            $table->string('source_reference', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'scope', 'reporting_period']);
        });

        Schema::create('esg_carbon_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('certificate_number')->unique();
            $table->string('registry'); // VERRA, GOLD_STANDARD, IDX_CARBON
            $table->string('project_name');
            $table->string('project_type'); // reforestation, renewable_energy, methane_capture
            $table->integer('vintage_year');
            $table->decimal('quantity_co2e_tons', 12, 4);
            $table->decimal('cost_per_ton_idr', 14, 2);
            $table->bigInteger('total_cost_idr');
            $table->string('status')->default('active'); // active, retired, expired
            $table->timestamps();
        });

        Schema::create('esg_offset_retirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('retirement_number')->unique();
            $table->foreignUuid('carbon_credit_id')->constrained('esg_carbon_credits')->cascadeOnDelete();
            $table->string('entity_id');
            $table->decimal('retired_quantity_tons', 12, 4);
            $table->string('reason');
            $table->date('retired_at');
            $table->string('certificate_url')->nullable();
            $table->timestamps();
        });

        Schema::create('esg_supplier_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('supplier_id');
            $table->string('evaluation_year', 4);
            $table->integer('environmental_score'); // 0-100
            $table->integer('social_score'); // 0-100
            $table->integer('governance_score'); // 0-100
            $table->decimal('overall_score', 5, 2);
            $table->string('certification_list')->nullable(); // FSC, RSPO, ISO14001, PROPER_HIJAU
            $table->string('rating_level'); // LEAD, ADVANCED, COMPLIANT, HIGH_RISK
            $table->text('audit_notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'evaluation_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_supplier_scores');
        Schema::dropIfExists('esg_offset_retirements');
        Schema::dropIfExists('esg_carbon_credits');
        Schema::dropIfExists('esg_emissions');
    }
};
