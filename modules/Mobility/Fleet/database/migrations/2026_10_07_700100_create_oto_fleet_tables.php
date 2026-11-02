<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oto_fleet_contracts')) {
            Schema::create('oto_fleet_contracts', function (Blueprint $table) {
                $table->id();
                $table->string('contract_number')->unique();
                $table->unsignedBigInteger('party_id')->index(); // lessee
                $table->unsignedInteger('duration_months')->default(36);
                $table->unsignedInteger('total_units')->default(1);
                $table->unsignedBigInteger('monthly_rental_idr');
                $table->unsignedBigInteger('total_lease_value_idr');
                $table->unsignedBigInteger('present_value_liability_idr')->default(0);
                $table->unsignedBigInteger('remaining_liability_idr')->default(0);
                $table->unsignedBigInteger('accumulated_amortized_idr')->default(0);
                $table->unsignedBigInteger('security_deposit_idr')->default(0);
                $table->unsignedInteger('max_downtime_hours_per_month')->default(24);
                $table->boolean('requires_four_eyes')->default(false);
                $table->foreignId('approved_by_primary_id')->nullable()->constrained('users');
                $table->foreignId('approved_by_secondary_id')->nullable()->constrained('users');
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status')->default('active'); // pending_approval, active, completed, terminated
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_fleet_contract_units')) {
            Schema::create('oto_fleet_contract_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('oto_fleet_contracts')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('core_vehicles');
                $table->unsignedBigInteger('baseline_odometer_km')->default(0);
                $table->unsignedBigInteger('current_odometer_km')->default(0);
                $table->unsignedBigInteger('max_km_per_year')->default(30000);
                $table->unsignedInteger('downtime_hours_recorded')->default(0);
                $table->text('condition_report_notes')->nullable();
                $table->string('status')->default('assigned'); // assigned, returned, replaced
                $table->timestamps();

                $table->unique(['contract_id', 'vehicle_id']);
                $table->index(['vehicle_id', 'status'], 'oto_flt_unit_veh_idx');
            });
        }

        if (! Schema::hasTable('oto_fleet_sla_logs')) {
            Schema::create('oto_fleet_sla_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('oto_fleet_contracts')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('core_vehicles');
                $table->string('incident_month', 7); // YYYY-MM
                $table->unsignedInteger('telematics_grounded_hours')->default(0);
                $table->unsignedInteger('workshop_service_hours')->default(0);
                $table->unsignedInteger('total_downtime_hours')->default(0);
                $table->unsignedInteger('breach_hours')->default(0);
                $table->unsignedBigInteger('penalty_credit_idr')->default(0);
                $table->boolean('is_compensated')->default(false);
                $table->string('idempotency_key', 64)->unique();
                $table->timestamps();

                $table->index(['contract_id', 'incident_month'], 'oto_flt_sla_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_fleet_sla_logs');
        Schema::dropIfExists('oto_fleet_contract_units');
        Schema::dropIfExists('oto_fleet_contracts');
    }
};
