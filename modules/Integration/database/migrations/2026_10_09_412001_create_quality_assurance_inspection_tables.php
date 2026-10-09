<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_code')->unique();
            $table->string('lot_or_unit_id');
            $table->string('inspector_id');
            $table->boolean('inspector_qualified')->default(true); // 412.1 & 412.4
            $table->string('tool_calibration_cert')->nullable(); // 412.2 & 412.4
            $table->boolean('calibration_valid')->default(true); // 412.4 calibration gate
            $table->string('result')->default('pending'); // pass, fail, pending
            $table->boolean('is_reinspected_independently')->default(false); // 412.5 edge case
            $table->timestamps();
        });

        Schema::create('ops_cost_of_quality_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_code')->unique();
            $table->string('business_line');
            $table->decimal('prevention_cost', 18, 2)->default(0.00);
            $table->decimal('appraisal_cost', 18, 2)->default(0.00);
            $table->decimal('internal_failure_cost', 18, 2)->default(0.00);
            $table->decimal('external_failure_cost', 18, 2)->default(0.00);
            $table->decimal('total_coq', 18, 2); // 412.3 & 412.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_cost_of_quality_records');
        Schema::dropIfExists('ops_quality_inspections');
    }
};
