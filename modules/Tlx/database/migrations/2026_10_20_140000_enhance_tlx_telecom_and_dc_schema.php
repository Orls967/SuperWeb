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
        // 1. Tambah kolom pada tlx_iot_devices
        Schema::table('tlx_iot_devices', function (Blueprint $table) {
            $table->string('device_code')->nullable()->unique('tlx_iot_dev_code_uniq');
            $table->string('using_module')->nullable();
            $table->bigInteger('monthly_fee_minor')->default(0);
            $table->string('idempotency_key')->nullable()->unique('tlx_iot_dev_idemp_uniq');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('retired_at')->nullable();
        });

        // 2. Tambah kolom pada tlx_iot_connectivity_invoices
        Schema::table('tlx_iot_connectivity_invoices', function (Blueprint $table) {
            $table->bigInteger('total_device_fee_minor')->default(0);
            $table->bigInteger('total_quota_mb')->default(0);
            $table->bigInteger('excess_mb')->default(0);
            $table->bigInteger('excess_charge_minor')->default(0);
            $table->foreignId('intercompany_transaction_id')->nullable()->constrained('ic_transactions', indexName: 'tlx_conn_inv_ic_tx_fk')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique('tlx_conn_inv_idemp_uniq');
            $table->string('status')->default('ISSUED');
        });

        // 3. Tambah kolom pada tlx_colocation_contracts
        Schema::table('tlx_colocation_contracts', function (Blueprint $table) {
            $table->integer('power_commit_kw')->default(0);
            $table->bigInteger('excess_power_rate_per_kwh_minor')->default(0);
            $table->string('idempotency_key')->nullable()->unique('tlx_colo_cntr_idemp_uniq');
        });

        // 4. Tabel tlx_sim_profiles
        Schema::create('tlx_sim_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('iccid')->unique('tlx_sim_iccid_uniq');
            $table->string('imsi')->unique('tlx_sim_imsi_uniq');
            $table->string('msisdn')->unique('tlx_sim_msisdn_uniq');
            $table->uuid('assigned_device_id')->nullable();
            $table->string('plan_code');
            $table->bigInteger('monthly_quota_mb')->default(0);
            $table->string('status')->default('AVAILABLE'); // AVAILABLE, ALLOCATED, DEACTIVATED
            $table->timestamps();

            $table->foreign('assigned_device_id', 'tlx_sim_dev_fk')
                ->references('id')
                ->on('tlx_iot_devices')
                ->nullOnDelete();
        });

        // 5. Tabel tlx_cdrs (append-only)
        Schema::create('tlx_cdrs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->string('session_id');
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->bigInteger('bytes_transferred');
            $table->bigInteger('rated_bytes');
            $table->integer('duration_seconds');
            $table->timestamps();

            $table->foreign('device_id', 'tlx_cdr_dev_fk')
                ->references('id')
                ->on('tlx_iot_devices')
                ->cascadeOnDelete();

            $table->unique(['device_id', 'session_id'], 'tlx_cdrs_dev_sess_uniq');
        });

        // 6. Tabel tlx_dc_power_readings (append-only)
        Schema::create('tlx_dc_power_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dc_id');
            $table->timestamp('reading_at');
            $table->bigInteger('total_facility_energy_kwh');
            $table->bigInteger('it_equipment_energy_kwh');
            $table->integer('peak_power_kw');
            $table->timestamps();

            $table->foreign('dc_id', 'tlx_dc_pwr_dc_fk')
                ->references('id')
                ->on('tlx_data_centers')
                ->cascadeOnDelete();

            $table->index(['dc_id', 'reading_at'], 'tlx_dc_pwr_dc_time_idx');
        });

        // 7. Tabel tlx_colocation_invoices
        Schema::create('tlx_colocation_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique('tlx_colo_inv_no_uniq');
            $table->uuid('contract_id');
            $table->string('tenant_party_id');
            $table->string('billing_period', 7); // YYYY-MM
            $table->bigInteger('rack_fee_minor');
            $table->bigInteger('committed_power_charge_minor')->default(0);
            $table->bigInteger('excess_power_charge_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->string('idempotency_key')->nullable()->unique('tlx_colo_inv_idemp_uniq');
            $table->string('status')->default('ISSUED'); // ISSUED, SETTLED
            $table->timestamps();

            $table->foreign('contract_id', 'tlx_colo_inv_cntr_fk')
                ->references('id')
                ->on('tlx_colocation_contracts')
                ->cascadeOnDelete();

            $table->index(['tenant_party_id', 'billing_period'], 'tlx_colo_inv_tenant_period_idx');
        });

        // 8. Tabel tlx_network_elements
        Schema::create('tlx_network_elements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('element_code')->unique('tlx_net_elem_code_uniq');
            $table->string('element_type'); // CORE_ROUTER, AGGREGATION_SWITCH, SITE, LINK, CELL_TOWER
            $table->uuid('parent_element_id')->nullable();
            $table->string('status')->default('OPERATIONAL'); // OPERATIONAL, DEGRADED, DOWN
            $table->timestamps();

            $table->foreign('parent_element_id', 'tlx_net_elem_parent_fk')
                ->references('id')
                ->on('tlx_network_elements')
                ->nullOnDelete();
        });

        // 9. Tabel tlx_noc_tickets
        Schema::create('tlx_noc_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->unique('tlx_noc_tkt_no_uniq');
            $table->uuid('root_element_id');
            $table->string('severity')->default('CRITICAL');
            $table->string('status')->default('OPEN'); // OPEN, ACKNOWLEDGED, RESOLVED, CLOSED
            $table->integer('mttr_minutes')->nullable();
            $table->integer('sla_target_minutes')->default(120);
            $table->boolean('is_sla_breached')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('root_element_id', 'tlx_noc_tkt_root_fk')
                ->references('id')
                ->on('tlx_network_elements')
                ->cascadeOnDelete();
        });

        // 10. Tabel tlx_noc_alarms (append-only)
        Schema::create('tlx_noc_alarms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alarm_code')->unique('tlx_noc_alm_code_uniq');
            $table->uuid('element_id');
            $table->string('alarm_type'); // LINK_DOWN, POWER_FAIL, BGP_DOWN, HARDWARE_FAIL
            $table->string('severity')->default('CRITICAL'); // CRITICAL, MAJOR, MINOR
            $table->boolean('is_suppressed')->default(false);
            $table->uuid('suppressed_by_element_id')->nullable();
            $table->uuid('ticket_id')->nullable();
            $table->timestamp('raised_at');
            $table->timestamps();

            $table->foreign('element_id', 'tlx_noc_alm_elem_fk')
                ->references('id')
                ->on('tlx_network_elements')
                ->cascadeOnDelete();

            $table->foreign('suppressed_by_element_id', 'tlx_noc_alm_supp_fk')
                ->references('id')
                ->on('tlx_network_elements')
                ->nullOnDelete();

            $table->foreign('ticket_id', 'tlx_noc_alm_tkt_fk')
                ->references('id')
                ->on('tlx_noc_tickets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tlx_noc_alarms');
        Schema::dropIfExists('tlx_noc_tickets');
        Schema::dropIfExists('tlx_network_elements');
        Schema::dropIfExists('tlx_colocation_invoices');
        Schema::dropIfExists('tlx_dc_power_readings');
        Schema::dropIfExists('tlx_cdrs');
        Schema::dropIfExists('tlx_sim_profiles');

        Schema::table('tlx_colocation_contracts', function (Blueprint $table) {
            $table->dropColumn(['power_commit_kw', 'excess_power_rate_per_kwh_minor', 'idempotency_key']);
        });

        Schema::table('tlx_iot_connectivity_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'total_device_fee_minor',
                'total_quota_mb',
                'excess_mb',
                'excess_charge_minor',
                'intercompany_transaction_id',
                'idempotency_key',
                'status',
            ]);
        });

        Schema::table('tlx_iot_devices', function (Blueprint $table) {
            $table->dropColumn([
                'device_code',
                'using_module',
                'monthly_fee_minor',
                'idempotency_key',
                'activated_at',
                'suspended_at',
                'retired_at',
            ]);
        });
    }
};
