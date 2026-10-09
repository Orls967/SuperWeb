<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_workforce_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_code')->unique();
            $table->string('employee_id');
            $table->date('shift_date');
            $table->integer('scheduled_hours');
            $table->integer('rest_hours_before_shift'); // 415.1 (must be >= 11 hours)
            $table->boolean('credentials_valid')->default(true);
            $table->boolean('rule_violation_detected')->default(false); // 415.4
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('ops_labor_time_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('exception_code')->unique();
            $table->foreignId('schedule_id')->constrained('ops_workforce_schedules')->cascadeOnDelete();
            $table->integer('actual_hours_worked');
            $table->integer('overtime_hours')->default(0);
            $table->decimal('overtime_premium_amount', 18, 2)->default(0.00);
            $table->boolean('is_approved_by_manager')->default(false); // 415.2, 415.4
            $table->string('approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_labor_time_exceptions');
        Schema::dropIfExists('ops_workforce_schedules');
    }
};
