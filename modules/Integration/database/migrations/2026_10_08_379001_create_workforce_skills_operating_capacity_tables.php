<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_workforce_cross_line_deployments', function (Blueprint $table) {
            $table->id();
            $table->string('deployment_code')->unique();
            $table->string('worker_id');
            $table->string('target_line_of_business');
            $table->boolean('qualification_gate_passed')->default(false); // 379.2 & 379.4
            $table->boolean('rest_rules_respected')->default(true); // 379.2 & 379.4
            $table->boolean('deployment_approved')->default(false);
            $table->timestamps();
        });

        Schema::create('global_operating_capacity_promises', function (Blueprint $table) {
            $table->id();
            $table->string('promise_code')->unique();
            $table->string('facility_or_site_id');
            $table->integer('available_staff_count');
            $table->integer('promised_capacity_units');
            $table->boolean('capacity_reduced_due_to_shortage')->default(false); // 379.3, 379.4, 379.5 Edge case
            $table->boolean('false_promise_prevented')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_operating_capacity_promises');
        Schema::dropIfExists('global_workforce_cross_line_deployments');
    }
};
