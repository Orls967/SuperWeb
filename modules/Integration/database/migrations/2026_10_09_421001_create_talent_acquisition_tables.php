<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_talent_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_code')->unique();
            $table->string('role_family'); // engineering, logistics_driver, culinary_chef, nurse
            $table->integer('target_headcount');
            $table->string('source_channel'); // linkedin, jobstreet, campus_recruiting, referral
            $table->decimal('cost_per_hire', 18, 2)->default(0.00);
            $table->integer('time_to_fill_days')->default(0);
            $table->timestamps();
        });

        Schema::create('hcm_candidate_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_code')->unique();
            $table->foreignId('requisition_id')->constrained('hcm_talent_requisitions')->cascadeOnDelete();
            $table->string('candidate_name');
            $table->decimal('assessment_score', 5, 2);
            $table->boolean('fairness_bias_checked')->default(true); // 421.1, 421.4, 421.5
            $table->boolean('privacy_retention_cleared')->default(false); // 421.3 data retention
            $table->string('status')->default('screened'); // screened, offered, rejected, hired
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_candidate_applications');
        Schema::dropIfExists('hcm_talent_requisitions');
    }
};
