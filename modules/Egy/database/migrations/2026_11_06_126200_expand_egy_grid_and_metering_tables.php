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
        if (! Schema::hasTable('egy_parameters')) {
            Schema::create('egy_parameters', function (Blueprint $table) {
                $table->id();
                $table->string('parameter_key');
                $table->unsignedInteger('version')->default(1);
                $table->text('value');
                $table->string('unit');
                $table->timestamp('effective_from');
                $table->timestamp('effective_to')->nullable();
                $table->string('simulation_label')->default('S1');
                $table->json('metadata')->nullable();
                $table->string('confirmed_by')->nullable();
                $table->timestamps();

                $table->unique(['parameter_key', 'version'], 'egy_param_key_ver_unique');
            });
        }

        Schema::table('egy_generation_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('egy_generation_assets', 'capacity_mw')) {
                $table->unsignedInteger('capacity_mw')->default(0);
            }
            if (! Schema::hasColumn('egy_generation_assets', 'available_capacity_mw')) {
                $table->unsignedInteger('available_capacity_mw')->default(0);
            }
            if (! Schema::hasColumn('egy_generation_assets', 'min_stable_mw')) {
                $table->unsignedInteger('min_stable_mw')->default(0);
            }
            if (! Schema::hasColumn('egy_generation_assets', 'marginal_cost_idr')) {
                $table->unsignedBigInteger('marginal_cost_idr')->default(0);
            }
            if (! Schema::hasColumn('egy_generation_assets', 'fuel_type')) {
                $table->string('fuel_type')->nullable();
            }
            if (! Schema::hasColumn('egy_generation_assets', 'is_must_run')) {
                $table->boolean('is_must_run')->default(false);
            }
        });

        Schema::table('egy_smart_meters', function (Blueprint $table) {
            if (! Schema::hasColumn('egy_smart_meters', 'meter_code')) {
                $table->string('meter_code')->nullable()->index();
            }
            if (! Schema::hasColumn('egy_smart_meters', 'utility_type')) {
                $table->string('utility_type')->default('electricity')->index();
            }
            if (! Schema::hasColumn('egy_smart_meters', 'property_id')) {
                $table->string('property_id')->nullable()->index();
            }
            if (! Schema::hasColumn('egy_smart_meters', 'node_id')) {
                $table->string('node_id')->nullable();
            }
            if (! Schema::hasColumn('egy_smart_meters', 'customer_party_id')) {
                $table->string('customer_party_id')->nullable()->index();
            }
            if (! Schema::hasColumn('egy_smart_meters', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        Schema::table('egy_meter_readings', function (Blueprint $table) {
            if (! Schema::hasColumn('egy_meter_readings', 'consumption_kwh')) {
                $table->unsignedBigInteger('consumption_kwh')->default(0);
            }
            if (! Schema::hasColumn('egy_meter_readings', 'production_kwh')) {
                $table->unsignedBigInteger('production_kwh')->default(0);
            }
            if (! Schema::hasColumn('egy_meter_readings', 'peak_kw')) {
                $table->unsignedBigInteger('peak_kw')->default(0);
            }
            if (! Schema::hasColumn('egy_meter_readings', 'power_factor_bps')) {
                $table->unsignedInteger('power_factor_bps')->default(10000);
            }
            if (! Schema::hasColumn('egy_meter_readings', 'is_reversal')) {
                $table->boolean('is_reversal')->default(false);
            }
            if (! Schema::hasColumn('egy_meter_readings', 'reversal_for_id')) {
                $table->string('reversal_for_id')->nullable();
            }
            if (! Schema::hasColumn('egy_meter_readings', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
        });

        Schema::table('egy_utility_consolidated_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('egy_utility_consolidated_invoices', 'customer_party_id')) {
                $table->string('customer_party_id')->nullable()->index();
            }
            if (! Schema::hasColumn('egy_utility_consolidated_invoices', 'period')) {
                $table->string('period')->nullable()->index();
            }
            if (! Schema::hasColumn('egy_utility_consolidated_invoices', 'total_amount_idr')) {
                $table->unsignedBigInteger('total_amount_idr')->default(0);
            }
            if (! Schema::hasColumn('egy_utility_consolidated_invoices', 'demand_charge_idr')) {
                $table->unsignedBigInteger('demand_charge_idr')->default(0);
            }
            if (! Schema::hasColumn('egy_utility_consolidated_invoices', 'net_metering_credit_idr')) {
                $table->unsignedBigInteger('net_metering_credit_idr')->default(0);
            }
            if (! Schema::hasColumn('egy_utility_consolidated_invoices', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
        });

        Schema::table('egy_ppa_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('egy_ppa_contracts', 'generator_party_id')) {
                $table->string('generator_party_id')->nullable();
            }
            if (! Schema::hasColumn('egy_ppa_contracts', 'offtaker_party_id')) {
                $table->string('offtaker_party_id')->nullable();
            }
            if (! Schema::hasColumn('egy_ppa_contracts', 'capacity_kw')) {
                $table->unsignedInteger('capacity_kw')->default(0);
            }
            if (! Schema::hasColumn('egy_ppa_contracts', 'rate_per_kwh_idr')) {
                $table->unsignedBigInteger('rate_per_kwh_idr')->default(0);
            }
            if (! Schema::hasColumn('egy_ppa_contracts', 'valid_from')) {
                $table->date('valid_from')->nullable();
            }
            if (! Schema::hasColumn('egy_ppa_contracts', 'valid_to')) {
                $table->date('valid_to')->nullable();
            }
        });

        if (! Schema::hasTable('egy_dispatch_runs')) {
            Schema::create('egy_dispatch_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('run_number')->unique();
                $table->timestamp('hour_timestamp');
                $table->unsignedInteger('load_mw');
                $table->unsignedInteger('total_dispatched_mw');
                $table->unsignedInteger('total_curtailed_mw')->default(0);
                $table->unsignedBigInteger('total_cost_thousand_idr')->default(0);
                $table->string('status')->default('dispatched'); // scheduled, dispatched, verified
                $table->string('idempotency_key')->unique();
                $table->timestamps();

                $table->index(['hour_timestamp', 'status']);
            });
        }

        if (! Schema::hasTable('egy_dispatch_orders')) {
            Schema::create('egy_dispatch_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('dispatch_run_id')->constrained('egy_dispatch_runs')->cascadeOnDelete();
                $table->string('generation_asset_id');
                $table->unsignedInteger('requested_mw')->default(0);
                $table->unsignedInteger('dispatched_mw');
                $table->unsignedInteger('curtailed_mw')->default(0);
                $table->unsignedBigInteger('marginal_cost_idr');
                $table->unsignedBigInteger('order_cost_thousand_idr')->default(0);
                $table->string('status')->default('accepted');
                $table->timestamps();

                $table->index(['dispatch_run_id', 'generation_asset_id']);
            });
        }

        if (! Schema::hasTable('egy_ppa_settlements')) {
            Schema::create('egy_ppa_settlements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('settlement_number')->unique();
                $table->foreignUuid('ppa_contract_id')->constrained('egy_ppa_contracts')->cascadeOnDelete();
                $table->string('period'); // YYYY-MM
                $table->unsignedBigInteger('delivered_kwh');
                $table->unsignedBigInteger('rate_per_kwh_idr');
                $table->unsignedBigInteger('total_payout_idr');
                $table->string('status')->default('settled'); // draft, settled, paid
                $table->string('idempotency_key')->unique();
                $table->timestamps();

                $table->index(['ppa_contract_id', 'period']);
            });
        }

        if (! Schema::hasTable('egy_demand_response_events')) {
            Schema::create('egy_demand_response_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('event_code')->unique();
                $table->unsignedBigInteger('site_asset_id');
                $table->date('event_date');
                $table->timestamp('peak_window_start');
                $table->timestamp('peak_window_end');
                $table->unsignedBigInteger('shifted_load_kwh');
                $table->unsignedBigInteger('cost_savings_idr');
                $table->unsignedBigInteger('total_reward_idr');
                $table->unsignedBigInteger('emission_reduction_g_co2e')->default(0);
                $table->string('status')->default('settled');
                $table->string('idempotency_key')->unique();
                $table->timestamps();

                $table->index(['site_asset_id', 'event_date']);
            });
        }

        if (! Schema::hasTable('egy_demand_response_allocations')) {
            Schema::create('egy_demand_response_allocations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('demand_response_event_id')->constrained('egy_demand_response_events')->cascadeOnDelete();
                $table->string('beneficiary_party_id');
                $table->unsignedInteger('proportion_bps');
                $table->unsignedBigInteger('allocated_reward_idr');
                $table->string('status')->default('allocated');
                $table->timestamps();

                $table->index(['demand_response_event_id', 'beneficiary_party_id'], 'egy_dr_alloc_event_party_idx');
            });
        }

        if (! Schema::hasTable('egy_status_transitions')) {
            Schema::create('egy_status_transitions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('entity_type', 64);
                $table->string('entity_id', 64);
                $table->string('from_status', 32);
                $table->string('to_status', 32);
                $table->string('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->string('triggered_by')->nullable();
                $table->timestamp('transitioned_at');
                $table->timestamps();

                $table->index(['entity_type', 'entity_id', 'transitioned_at'], 'egy_stat_trans_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('egy_status_transitions');
        Schema::dropIfExists('egy_demand_response_allocations');
        Schema::dropIfExists('egy_demand_response_events');
        Schema::dropIfExists('egy_ppa_settlements');
        Schema::dropIfExists('egy_dispatch_orders');
        Schema::dropIfExists('egy_dispatch_runs');
        Schema::dropIfExists('egy_parameters');
    }
};
