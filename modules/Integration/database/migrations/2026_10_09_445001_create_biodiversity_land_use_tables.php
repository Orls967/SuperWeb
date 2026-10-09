<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_biodiversity_land_plots', function (Blueprint $table) {
            $table->id();
            $table->string('plot_code')->unique();
            $table->string('location_name');
            $table->string('hierarchy_stage'); // avoid, minimize, restore, offset_last_resort (445.1, 445.6)
            $table->boolean('disturbance_detected')->default(false); // 445.2, 445.5
            $table->string('remediation_task_id')->nullable(); // 445.5
            $table->boolean('remediation_completed')->default(true);
            $table->timestamps();
        });

        Schema::create('esg_biodiversity_offset_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('title');
            $table->boolean('avoidance_proven_infeasible')->default(false); // 445.4, 445.6
            $table->boolean('additionality_verified')->default(false); // 445.3
            $table->boolean('permanence_verified')->default(false);
            $table->boolean('community_consent_granted')->default(false);
            $table->boolean('credit_issuance_authorized')->default(false); // 445.3, 445.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_biodiversity_offset_projects');
        Schema::dropIfExists('esg_biodiversity_land_plots');
    }
};
