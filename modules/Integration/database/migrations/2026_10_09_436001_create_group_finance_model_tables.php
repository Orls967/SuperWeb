<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_group_service_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('catalog_code')->unique();
            $table->string('service_type'); // close, reporting, tax, treasury, controlling, shared_services (436.1)
            $table->string('entity_code');
            $table->integer('sla_hours');
            $table->decimal('cost_allocation_rate', 18, 2);
            $table->timestamps();
        });

        Schema::create('fin_close_orchestration_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_code')->unique();
            $table->string('period'); // e.g. 2026-M09
            $table->string('entity_code');
            $table->string('prerequisite_task_code')->nullable(); // 436.2 dependency graph
            $table->boolean('automated_check_passed')->default(false);
            $table->boolean('has_exception')->default(false);
            $table->string('exception_route')->nullable(); // 436.2, 436.5
            $table->string('status')->default('pending'); // pending, completed, escalated, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_close_orchestration_tasks');
        Schema::dropIfExists('fin_group_service_catalogs');
    }
};
