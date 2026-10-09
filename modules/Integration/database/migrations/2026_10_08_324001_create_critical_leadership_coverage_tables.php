<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('critical_leadership_role_coverages', function (Blueprint $table) {
            $table->id();
            $table->string('role_code')->unique();
            $table->string('site_code');
            $table->string('current_holder_id');
            $table->boolean('has_single_person_dependency')->default(false); // 324.1
            $table->boolean('marked_as_organizational_risk')->default(false); // 324.6 Risk
            $table->string('external_hire_search_status')->nullable(); // 324.5 Edge case
            $table->timestamps();
        });

        Schema::create('critical_acting_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_code')->unique();
            $table->string('role_code')->index();
            $table->string('acting_candidate_id')->index();
            $table->decimal('readiness_qualification_score', 4, 1);
            $table->boolean('candidate_certified')->default(false);
            $table->boolean('candidate_consent_granted')->default(false);
            $table->integer('max_appointment_duration_days')->default(90); // 324.4 Time-bounded
            $table->boolean('board_committee_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_acting_appointments');
        Schema::dropIfExists('critical_leadership_role_coverages');
    }
};
