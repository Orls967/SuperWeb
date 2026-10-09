<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_learning_courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_code');
            $table->string('version', 16);
            $table->string('title');
            $table->string('type'); // COMPLIANCE, TECHNICAL, LEADERSHIP, MICRO
            $table->decimal('learning_hours', 5, 2)->default(1.0);
            $table->string('status')->default('ACTIVE'); // ACTIVE, RETIRED
            $table->timestamps();
            $table->unique(['course_code', 'version']);
        });

        Schema::create('edu_course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('enrollment_code')->unique();
            $table->string('employee_id')->index();
            $table->string('course_code')->index();
            $table->string('locked_version', 16); // Immutable version lock per enrollment (227.7)
            $table->decimal('progress_pct', 5, 2)->default(0);
            $table->decimal('pre_test_score', 5, 2)->nullable();
            $table->decimal('post_test_score', 5, 2)->nullable();
            $table->string('status')->default('ENROLLED'); // ENROLLED, IN_PROGRESS, COMPLETED
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('edu_employee_certifications', function (Blueprint $table) {
            $table->id();
            $table->string('certification_code')->unique();
            $table->string('employee_id')->index();
            $table->string('skill_code')->index();
            $table->boolean('is_mandatory_for_role')->default(false);
            $table->date('expires_at');
            $table->string('status')->default('ACTIVE'); // ACTIVE, EXPIRED, REVOKED
            $table->boolean('assignment_hold')->default(false); // 227.6 edge case hold for expired mandatory cert
            $table->timestamps();
        });

        Schema::create('edu_skill_gap_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code')->index();
            $table->string('skill_code')->index();
            $table->decimal('required_proficiency', 3, 2);
            $table->decimal('average_proficiency', 3, 2);
            $table->decimal('gap_score', 3, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_skill_gap_assessments');
        Schema::dropIfExists('edu_employee_certifications');
        Schema::dropIfExists('edu_course_enrollments');
        Schema::dropIfExists('edu_learning_courses');
    }
};
