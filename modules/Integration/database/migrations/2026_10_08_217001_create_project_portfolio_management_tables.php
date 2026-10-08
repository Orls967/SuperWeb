<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 217.2: Task dependency acyclic validation & schedule tracking
        Schema::create('ops_ppm_project_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_code')->unique();
            $table->string('project_code');
            $table->string('task_name');
            $table->string('predecessor_task_code')->nullable();
            $table->integer('duration_days')->default(1);
            $table->timestamps();
        });

        // 217.4: Project scope change requests requiring explicit budget & executive approval
        Schema::create('ops_ppm_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('change_request_code')->unique();
            $table->string('project_code');
            $table->text('scope_modification_description');
            $table->decimal('budget_impact_idr', 18, 2);
            $table->integer('schedule_impact_days');
            $table->string('approval_status')->default('SUBMITTED'); // SUBMITTED, APPROVED, EXECUTED
            $table->string('approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_ppm_change_requests');
        Schema::dropIfExists('ops_ppm_project_tasks');
    }
};
