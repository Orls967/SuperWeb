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
        Schema::create('tlx_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_code')->unique();
            $table->string('site_name');
            $table->string('site_type'); // TOWER, POP, DATA_CENTER
            $table->string('region');
            $table->double('latitude', 10, 6)->nullable();
            $table->double('longitude', 10, 6)->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['site_type', 'region']);
        });

        Schema::create('tlx_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('link_code')->unique();
            $table->string('origin_site_id');
            $table->string('dest_site_id');
            $table->string('link_medium'); // FIBER, MICROWAVE, SATELLITE
            $table->double('bandwidth_capacity_gbps', 8, 2);
            $table->double('allocated_bandwidth_gbps', 8, 2)->default(0.0);
            $table->double('target_sla_uptime_pct', 5, 2)->default(99.90);
            $table->double('actual_sla_uptime_pct', 5, 2)->default(100.00);
            $table->integer('downtime_minutes_month')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['origin_site_id', 'dest_site_id']);
        });

        Schema::create('tlx_iot_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_uuid')->unique();
            $table->string('device_type'); // TELEMATICS, BUILDING_SENSOR, ENERGY_METER, MINING_SENSOR, PATIENT_MONITOR
            $table->string('owner_entity_id');
            $table->string('active_sim_iccid')->nullable();
            $table->double('data_quota_mb_monthly', 10, 2)->default(1000.0);
            $table->double('consumed_mb_monthly', 10, 2)->default(0.0);
            $table->bigInteger('rate_per_mb_minor')->default(50); // 50 IDR/MB
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['device_type', 'owner_entity_id']);
        });

        Schema::create('tlx_iot_connectivity_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->string('owner_entity_id');
            $table->string('billing_period'); // YYYY-MM
            $table->integer('active_device_count');
            $table->double('total_consumed_mb', 12, 2);
            $table->bigInteger('total_charge_minor');
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['owner_entity_id', 'billing_period'], 'tlx_iot_inv_owner_period_idx');
        });

        Schema::create('tlx_noc_network_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->unique();
            $table->string('idempotency_alarm_key')->unique();
            $table->string('link_id')->nullable();
            $table->string('site_id')->nullable();
            $table->string('alarm_type'); // LINK_DOWN, LATENCY_SPIKE, PACKET_LOSS
            $table->string('severity')->default('CRITICAL');
            $table->string('correlated_line_incident_id')->nullable();
            $table->integer('mttr_minutes')->nullable();
            $table->string('status')->default('OPEN'); // OPEN, INVESTIGATING, RESOLVED
            $table->timestamp('raised_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['alarm_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tlx_noc_network_tickets');
        Schema::dropIfExists('tlx_iot_connectivity_invoices');
        Schema::dropIfExists('tlx_iot_devices');
        Schema::dropIfExists('tlx_links');
        Schema::dropIfExists('tlx_sites');
    }
};
