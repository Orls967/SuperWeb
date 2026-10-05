<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 40.1–40.7 OEE, pemeliharaan, sensor IoT simulasi, K3/HSE, energi/lingkungan.
return new class extends Migration
{
    public function up(): void
    {
        // 40.2/40.3 Work order pabrik + spare parts/tooling.
        Schema::create('mfg_maintenance_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('MWO/{ENT}/YYYY-NNNNN');
            $table->uuid('work_center_id');
            $table->uuid('asset_id')->nullable()->comment('ast_assets.id — link non-breaking');
            $table->string('kind', 16)->default('corrective')->comment('corrective, preventive, predictive, sensor_alarm');
            $table->string('status', 16)->default('open')->comment('open, in_progress, completed, cancelled');
            $table->string('priority', 12)->default('normal')->comment('low, normal, high, critical');
            $table->string('trigger_key', 80)->nullable()->unique()->comment('Idempoten untuk auto alarm');
            $table->string('description', 500);
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->bigInteger('labor_cost_idr')->default(0);
            $table->bigInteger('parts_cost_idr')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->cascadeOnDelete();
            $table->index(['status', 'priority', 'due_date']);
        });

        Schema::create('mfg_equipment_parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('work_center_id');
            $table->string('part_code', 60);
            $table->string('name', 160);
            $table->unsignedInteger('qty_per_equipment')->default(1);
            $table->decimal('min_stock', 18, 6)->default(0);
            $table->bigInteger('unit_cost_idr')->default(0);
            $table->timestamps();

            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->cascadeOnDelete();
            $table->unique(['work_center_id', 'part_code']);
        });

        Schema::create('mfg_maintenance_parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('maintenance_order_id');
            $table->unsignedBigInteger('equipment_part_id');
            $table->decimal('qty', 18, 6);
            $table->bigInteger('cost_idr')->default(0);
            $table->timestamps();

            $table->foreign('maintenance_order_id')->references('id')->on('mfg_maintenance_orders')->cascadeOnDelete();
            $table->foreign('equipment_part_id')->references('id')->on('mfg_equipment_parts')->cascadeOnDelete();
        });

        // 40.4 Sensor readings simulasi + alarm (thermal/vibration/current).
        Schema::create('mfg_sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->uuid('work_center_id');
            $table->string('sensor_code', 40);
            $table->string('metric', 24)->comment('temperature, vibration, current');
            $table->decimal('value', 18, 6);
            $table->string('unit', 16);
            $table->decimal('threshold_high', 18, 6)->nullable();
            $table->decimal('threshold_low', 18, 6)->nullable();
            $table->boolean('alarm')->default(false);
            $table->uuid('maintenance_order_id')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->cascadeOnDelete();
            $table->index(['work_center_id', 'metric', 'recorded_at']);
        });

        // 40.5 Agregat OEE per work center/bucket.
        Schema::create('mfg_oee_summaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('work_center_id');
            $table->date('period_date');
            $table->string('shift_code', 30)->nullable();
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('run_minutes')->default(0);
            $table->unsignedInteger('ideal_cycle_seconds')->default(0);
            $table->decimal('qty_total', 18, 6)->default(0);
            $table->decimal('qty_good', 18, 6)->default(0);
            $table->decimal('availability_percent', 8, 4)->default(0);
            $table->decimal('performance_percent', 8, 4)->default(0);
            $table->decimal('quality_percent', 8, 4)->default(0);
            $table->decimal('oee_percent', 8, 4)->default(0);
            $table->decimal('mtbf_hours', 12, 2)->default(0);
            $table->decimal('mttr_minutes', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->cascadeOnDelete();
            $table->unique(['work_center_id', 'period_date', 'shift_code']);
            $table->index(['period_date', 'oee_percent']);
        });

        // 40.6 K3/HSE: incident + permit kerja berisiko approval/validitas.
        Schema::create('mfg_hse_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('HSE/{ENT}/YYYY-NNNNN');
            $table->uuid('work_center_id')->nullable();
            $table->unsignedBigInteger('worker_id')->nullable();
            $table->string('kind', 20)->default('near_miss')->comment('incident, near_miss, injury, environmental');
            $table->string('severity', 16)->default('low')->comment('low, moderate, high, critical');
            $table->string('status', 16)->default('reported')->comment('reported, investigating, action, closed');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('investigation')->nullable();
            $table->text('corrective_action')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->nullOnDelete();
            $table->index(['status', 'severity']);
        });

        Schema::create('mfg_work_permits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('PTW/{ENT}/YYYY-NNNNN');
            $table->uuid('work_center_id');
            $table->string('permit_type', 24)->comment('hot_work, confined_space');
            $table->string('status', 16)->default('pending')->comment('pending, approved, active, expired, closed, rejected');
            $table->text('hazards')->nullable();
            $table->text('controls')->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->cascadeOnDelete();
            $table->index(['status', 'valid_until']);
        });

        // 40.7 Energi / lingkungan per order.
        Schema::create('mfg_resource_usages', function (Blueprint $table) {
            $table->id();
            $table->uuid('production_order_id');
            $table->uuid('work_center_id')->nullable();
            $table->string('resource', 16)->comment('electricity_kwh, water_liter, waste_kg');
            $table->decimal('qty', 18, 6);
            $table->string('unit', 16);
            $table->bigInteger('cost_idr')->default(0);
            $table->timestamp('recorded_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('production_order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->nullOnDelete();
            $table->index(['production_order_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_resource_usages');
        Schema::dropIfExists('mfg_work_permits');
        Schema::dropIfExists('mfg_hse_incidents');
        Schema::dropIfExists('mfg_oee_summaries');
        Schema::dropIfExists('mfg_sensor_readings');
        Schema::dropIfExists('mfg_maintenance_parts');
        Schema::dropIfExists('mfg_equipment_parts');
        Schema::dropIfExists('mfg_maintenance_orders');
    }
};
