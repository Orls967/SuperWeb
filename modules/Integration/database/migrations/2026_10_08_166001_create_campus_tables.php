<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 166.1: Campus institutions & academic cohorts
        Schema::create('camp_institutions', function (Blueprint $table) {
            $table->id();
            $table->string('institution_code')->unique();
            $table->string('name');
            $table->string('type'); // K12, VOCATIONAL, UNIVERSITY
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 166.3: Timetables & conflict detection
        Schema::create('camp_timetables', function (Blueprint $table) {
            $table->id();
            $table->string('institution_code');
            $table->string('room_id');
            $table->string('teacher_id');
            $table->string('subject_code');
            $table->integer('day_of_week'); // 1-7
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        // 166.3: Gradebook & locked transcripts with cryptographic hash
        Schema::create('camp_transcripts', function (Blueprint $table) {
            $table->id();
            $table->string('transcript_code')->unique();
            $table->unsignedBigInteger('student_id');
            $table->string('subject_code');
            $table->decimal('grade_score', 5, 2);
            $table->string('letter_grade', 2);
            $table->boolean('is_locked')->default(false);
            $table->string('certificate_hash')->nullable();
            $table->timestamps();
        });

        // 166.4: Tuition billing & scholarship allocations
        Schema::create('camp_tuition_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->unsignedBigInteger('student_id');
            $table->string('term_code');
            $table->decimal('gross_tuition_amount', 18, 2);
            $table->decimal('scholarship_aid_deduction', 18, 2)->default(0.00);
            $table->decimal('net_tuition_payable', 18, 2);
            $table->string('status')->default('ISSUED'); // ISSUED, PAID
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camp_tuition_invoices');
        Schema::dropIfExists('camp_transcripts');
        Schema::dropIfExists('camp_timetables');
        Schema::dropIfExists('camp_institutions');
    }
};
