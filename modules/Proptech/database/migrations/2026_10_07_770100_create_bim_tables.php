<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 77.1 BIM Models with versioning and hash-chain
        Schema::create('prp_bim_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 32);
            $table->string('project_code', 32);
            $table->integer('version')->default(1);
            $table->string('name', 128);
            $table->string('previous_hash', 64)->nullable();
            $table->string('current_hash', 64);
            $table->json('metadata')->nullable();
            $table->string('status', 32)->default('APPROVED'); // DRAFT, APPROVED, SUPERSEDED
            $table->timestamps();

            $table->unique(['project_code', 'version']);
        });

        // 77.1 & 77.2 Twin components (piping, duct, chiller, electrical) linked to WBS & sensors
        Schema::create('prp_twin_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bim_model_id');
            $table->string('component_code', 32)->unique();
            $table->string('wbs_node_code', 32)->nullable(); // EPC WBS linkage
            $table->string('category', 32); // HVAC_CHILLER, PIPING, DUCTWORK, ELECTRICAL, STRUCTURAL
            $table->string('name', 128);
            $table->string('location_spec', 128)->nullable(); // e.g. "Floor 2, Ceiling Plenum Zone A"
            $table->string('status', 32)->default('PLANNED'); // PLANNED, IN_PROGRESS, INSTALLED, OPERATIONAL
            $table->integer('completion_pct')->default(0);
            $table->unsignedBigInteger('linked_asset_id')->nullable();
            $table->unsignedBigInteger('linked_sensor_id')->nullable();
            $table->timestamps();

            $table->foreign('bim_model_id')->references('id')->on('prp_bim_models')->cascadeOnDelete();
        });

        // 77.1 & 77.3 Twin issues & maintenance impact
        Schema::create('prp_twin_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_code', 32)->unique();
            $table->unsignedBigInteger('component_id');
            $table->string('issue_type', 32); // CLASH, LEAK, DEFECT, MAINTENANCE_OVERDUE
            $table->string('severity', 16)->default('MEDIUM');
            $table->text('description');
            $table->unsignedBigInteger('facility_work_order_id')->nullable();
            $table->string('status', 32)->default('OPEN'); // OPEN, IN_REVIEW, RESOLVED
            $table->timestamps();

            $table->foreign('component_id')->references('id')->on('prp_twin_components')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prp_twin_issues');
        Schema::dropIfExists('prp_twin_components');
        Schema::dropIfExists('prp_bim_models');
    }
};
