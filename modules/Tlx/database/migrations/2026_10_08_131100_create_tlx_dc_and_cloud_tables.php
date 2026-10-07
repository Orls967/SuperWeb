<?php

declare(strict_types=1);

namespace Modules\Tlx\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tlx_data_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dc_code')->unique();
            $table->string('name');
            $table->string('city');
            $table->double('total_facility_power_kw', 10, 2);
            $table->double('it_load_power_kw', 10, 2);
            $table->double('measured_pue', 4, 2); // total power / IT load (e.g. 1.25)
            $table->integer('total_racks_capacity');
            $table->integer('occupied_racks');
            $table->timestamps();

            $table->index('city');
        });

        Schema::create('tlx_colocation_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->unique();
            $table->string('tenant_party_id');
            $table->string('dc_id');
            $table->integer('rack_units_allocated');
            $table->bigInteger('monthly_rack_fee_minor');
            $table->bigInteger('power_rate_per_kwh_minor');
            $table->double('monthly_kwh_consumed', 10, 2)->default(0.0);
            $table->string('port_status')->default('UP'); // UP, SUSPENDED, TERMINATED
            $table->boolean('is_overdue')->default(false);
            $table->timestamps();

            $table->index(['tenant_party_id', 'port_status']);
        });

        Schema::create('tlx_cloud_compute_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('instance_code')->unique();
            $table->string('consumer_entity_id');
            $table->string('sku_flavor'); // C2_STANDARD_4, M2_HIGHMEM_8, G2_GPU_ACCEL
            $table->integer('vcpus');
            $table->integer('ram_gb');
            $table->integer('storage_gb');
            $table->bigInteger('hourly_rate_minor');
            $table->integer('running_hours_billed')->default(0);
            $table->bigInteger('total_chargeback_minor')->default(0);
            $table->string('status')->default('RUNNING'); // RUNNING, STOPPED, TERMINATED
            $table->timestamps();

            $table->index(['consumer_entity_id', 'status']);
        });

        Schema::create('tlx_dr_restore_drills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('drill_code')->unique();
            $table->string('dataset_class'); // CRITICAL_FINANCE, HEALTH_EMR, GENERAL_CATALOG
            $table->integer('target_rpo_minutes');
            $table->integer('target_rto_minutes');
            $table->integer('actual_data_loss_minutes');
            $table->integer('actual_recovery_time_minutes');
            $table->boolean('is_sla_compliant')->default(true);
            $table->timestamp('drill_conducted_at');
            $table->timestamps();

            $table->index(['dataset_class', 'is_sla_compliant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tlx_dr_restore_drills');
        Schema::dropIfExists('tlx_cloud_compute_instances');
        Schema::dropIfExists('tlx_colocation_contracts');
        Schema::dropIfExists('tlx_data_centers');
    }
};
