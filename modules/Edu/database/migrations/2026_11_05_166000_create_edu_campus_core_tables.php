<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_campus_institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('institution_code', 32)->unique();
            $table->string('name', 255);
            $table->string('type', 32); // UNIVERSITY, VOCATIONAL, HIGH_SCHOOL
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('edu_campus_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained('edu_campus_institutions')->cascadeOnDelete();
            $table->string('term_code', 32)->unique();
            $table->string('name', 128); // e.g. Ganjil 2026/2027
            $table->string('academic_year', 16); // 2026/2027
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('edu_campus_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained('edu_campus_institutions')->cascadeOnDelete();
            $table->string('course_code', 32)->unique();
            $table->string('name', 255);
            $table->unsignedSmallInteger('credits'); // SKS (1-6)
            $table->string('prerequisite_course_code', 32)->nullable();
            $table->decimal('minimum_gpa_required', 3, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('edu_campus_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('edu_campus_courses')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('edu_campus_terms')->cascadeOnDelete();
            $table->string('class_code', 32)->unique();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Senin, ..., 7 = Minggu
            $table->unsignedSmallInteger('start_minute'); // Menit dari 00:00 (misal 08:00 = 480)
            $table->unsignedSmallInteger('end_minute');   // Menit dari 00:00 (misal 09:40 = 580)
            $table->string('room_code', 64);
            $table->string('lecturer_name', 128);
            $table->unsignedSmallInteger('max_capacity')->default(40);
            $table->unsignedSmallInteger('enrolled_count')->default(0);
            $table->timestamps();
        });

        Schema::create('edu_campus_krs_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('edu_campus_classes')->cascadeOnDelete();
            $table->unsignedBigInteger('student_user_id')->index();
            $table->string('status', 32)->default('approved'); // pending, approved, dropped
            $table->timestamps();

            $table->unique(['class_id', 'student_user_id']);
        });

        Schema::create('edu_campus_transcripts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_user_id')->index();
            $table->foreignId('course_id')->constrained('edu_campus_courses')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('edu_campus_terms')->cascadeOnDelete();
            $table->decimal('grade_point', 4, 2); // 4.00, 3.50, dll.
            $table->string('letter_grade', 4);    // A, B+, B, dll.
            $table->string('prev_hash', 64);
            $table->string('certificate_hash', 64);
            $table->boolean('is_locked')->default(true);
            $table->timestamps();

            $table->unique(['student_user_id', 'course_id', 'term_id']);
        });

        Schema::create('edu_campus_tuition_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_code', 64)->unique();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('edu_campus_terms')->cascadeOnDelete();
            $table->bigInteger('gross_amount_idr');
            $table->bigInteger('scholarship_deduction_idr')->default(0);
            $table->bigInteger('net_payable_idr');
            $table->string('status', 32)->default('unpaid'); // unpaid, paid, canceled
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_campus_tuition_invoices');
        Schema::dropIfExists('edu_campus_transcripts');
        Schema::dropIfExists('edu_campus_krs_enrollments');
        Schema::dropIfExists('edu_campus_classes');
        Schema::dropIfExists('edu_campus_courses');
        Schema::dropIfExists('edu_campus_terms');
        Schema::dropIfExists('edu_campus_institutions');
    }
};
