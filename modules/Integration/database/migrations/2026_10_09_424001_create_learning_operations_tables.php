<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_learning_courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_code')->unique();
            $table->string('title');
            $table->boolean('is_mandatory_compliance')->default(false); // 424.3
            $table->string('instructor_id');
            $table->boolean('instructor_qualified')->default(true); // 424.1, 424.5
            $table->string('materials_version')->default('1.0');
            $table->timestamps();
        });

        Schema::create('hcm_compliance_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_code')->unique();
            $table->foreignId('course_id')->constrained('hcm_learning_courses')->cascadeOnDelete();
            $table->string('employee_id');
            $table->date('due_date');
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_overdue')->default(false); // 424.3, 424.4
            $table->boolean('role_activity_blocked')->default(false); // 424.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_compliance_assignments');
        Schema::dropIfExists('hcm_learning_courses');
    }
};
