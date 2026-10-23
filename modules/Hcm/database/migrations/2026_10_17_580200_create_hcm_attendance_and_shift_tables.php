<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_shift_schedules')) {
            Schema::create('hcm_shift_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('shift_code', 30)->unique();
                $table->string('name', 80);
                $table->time('start_time');
                $table->time('end_time');
                $table->integer('break_minutes')->default(60);
                $table->boolean('is_night_shift')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hcm_attendance_logs')) {
            Schema::create('hcm_attendance_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('shift_id')->nullable();
                $table->date('log_date');
                $table->dateTime('clock_in')->nullable();
                $table->dateTime('clock_out')->nullable();
                $table->integer('late_minutes')->default(0);
                $table->decimal('overtime_hours', 5, 2)->default(0.00);
                $table->string('status', 30)->default('present'); // present, late, absent, on_leave
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('hcm_employees')->cascadeOnDelete();
                $table->foreign('shift_id')->references('id')->on('hcm_shift_schedules')->nullOnDelete();
                $table->unique(['employee_id', 'log_date']);
            });
        }

        if (! Schema::hasTable('hcm_leave_requests')) {
            Schema::create('hcm_leave_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->string('leave_type', 40)->default('annual'); // annual, sick, maternity, unpaid
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('total_days')->default(1);
                $table->string('reason', 255)->nullable();
                $table->string('status', 30)->default('pending'); // pending, approved, rejected
                $table->uuid('approved_by')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('hcm_employees')->cascadeOnDelete();
                $table->foreign('approved_by')->references('id')->on('hcm_employees')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_leave_requests');
        Schema::dropIfExists('hcm_attendance_logs');
        Schema::dropIfExists('hcm_shift_schedules');
    }
};
