<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_engineering_design_ecos', function (Blueprint $table) {
            $table->id();
            $table->string('eco_code')->unique();
            $table->string('component_name');
            $table->boolean('passed_fto_ip_review')->default(true); // 352.5 Edge case
            $table->boolean('engineer_reviewed_and_validated')->default(false); // 352.1 & 352.4
            $table->boolean('eco_workflow_adopted')->default(false);
            $table->timestamps();
        });

        Schema::create('copilot_code_generation_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_code')->unique();
            $table->string('repository_name');
            $table->boolean('human_approved')->default(false); // 352.2 & 352.4
            $table->boolean('ci_tests_passed')->default(false);
            $table->boolean('direct_production_write_attempted')->default(false); // 352.2 No direct write
            $table->boolean('merged_to_production')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_code_generation_proposals');
        Schema::dropIfExists('copilot_engineering_design_ecos');
    }
};
