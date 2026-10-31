<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oto_geofence_zones')) {
            Schema::create('oto_geofence_zones', function (Blueprint $table) {
                $table->id();
                $table->string('zone_code')->unique();
                $table->string('name');
                $table->json('polygon_json');
                $table->integer('min_lat_e6');
                $table->integer('max_lat_e6');
                $table->integer('min_lon_e6');
                $table->integer('max_lon_e6');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_fleet_invoices')) {
            Schema::create('oto_fleet_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->unique();
                $table->foreignId('contract_id')->constrained('oto_fleet_contracts')->cascadeOnDelete();
                $table->string('billing_period');
                $table->unsignedBigInteger('rental_fee_idr');
                $table->unsignedBigInteger('penalty_credit_idr')->default(0);
                $table->unsignedBigInteger('excess_mileage_fee_idr')->default(0);
                $table->unsignedBigInteger('total_billed_idr');
                $table->string('idempotency_key')->unique();
                $table->string('status')->default('ISSUED'); // ISSUED, PAID
                $table->timestamps();

                $table->index(['contract_id', 'billing_period']);
            });
        }

        if (! Schema::hasTable('oto_fleet_sla_breaches')) {
            Schema::create('oto_fleet_sla_breaches', function (Blueprint $table) {
                $table->id();
                $table->string('breach_uuid')->unique();
                $table->foreignId('contract_id')->constrained('oto_fleet_contracts')->cascadeOnDelete();
                $table->foreignId('unit_id')->constrained('oto_fleet_contract_units')->cascadeOnDelete();
                $table->date('incident_date');
                $table->unsignedInteger('downtime_hours');
                $table->string('breach_type')->default('DOWNTIME_EXCESS');
                $table->unsignedInteger('penalty_bps')->default(0);
                $table->unsignedBigInteger('penalty_amount_idr')->default(0);
                $table->foreignId('credited_invoice_id')->nullable()->constrained('oto_fleet_invoices')->nullOnDelete();
                $table->string('status')->default('RECORDED'); // RECORDED, CREDITED
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_fleet_amortization_schedules')) {
            Schema::create('oto_fleet_amortization_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('oto_fleet_contracts')->cascadeOnDelete();
                $table->unsignedInteger('period_month');
                $table->unsignedBigInteger('principal_idr');
                $table->unsignedBigInteger('interest_idr');
                $table->unsignedBigInteger('payment_idr');
                $table->unsignedBigInteger('ending_liability_idr');
                $table->boolean('is_posted')->default(false);
                $table->timestamps();

                $table->unique(['contract_id', 'period_month'], 'oto_fleet_amort_contract_period_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_fleet_amortization_schedules');
        Schema::dropIfExists('oto_fleet_sla_breaches');
        Schema::dropIfExists('oto_fleet_invoices');
        Schema::dropIfExists('oto_geofence_zones');
    }
};
