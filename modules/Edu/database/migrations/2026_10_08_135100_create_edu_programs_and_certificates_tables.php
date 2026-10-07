<?php

declare(strict_types=1);

namespace Modules\Edu\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 135.1 & 135.2 Educational programs & courses
        Schema::create('edu_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('program_code')->unique();
            $table->string('title');
            $table->string('industry_sector'); // AUTOMOTIVE, HOSPITALITY, MINING_HSE, HEALTHCARE, CULINARY
            $table->string('prerequisite_program_id')->nullable(); // Prerequisite validation
            $table->integer('total_sessions')->default(10);
            $table->bigInteger('tuition_fee_minor');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['industry_sector', 'status']);
        });

        // 135.1 Cohorts
        Schema::create('edu_cohorts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cohort_code')->unique();
            $table->string('program_id');
            $table->string('instructor_party_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('max_capacity')->default(30);
            $table->integer('enrolled_count')->default(0);
            $table->string('status')->default('OPEN'); // OPEN, IN_PROGRESS, COMPLETED
            $table->timestamps();

            $table->index(['program_id', 'status']);
        });

        // 135.3 Enrollments & pro-rata refunds
        Schema::create('edu_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('enrollment_code')->unique();
            $table->string('student_party_id');
            $table->string('cohort_id');
            $table->string('corporate_client_id')->nullable(); // For B2B corporate L&D
            $table->bigInteger('amount_paid_minor');
            $table->integer('sessions_attended')->default(0);
            $table->integer('total_sessions')->default(10);
            $table->bigInteger('refund_amount_minor')->default(0);
            $table->string('status')->default('ENROLLED'); // ENROLLED, GRADUATED, DROPPED_OUT, CANCELLED
            $table->timestamps();

            $table->index(['student_party_id', 'status']);
        });

        // 135.4 Hash-chain verifiable certificates & QR proof
        Schema::create('edu_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('certificate_number')->unique();
            $table->string('enrollment_id');
            $table->string('student_party_id');
            $table->string('program_id');
            $table->string('skill_competency_code'); // HSE_K3_MINING, AUTOSERVE_EV_TECH, etc.
            $table->string('certificate_hash')->unique(); // SHA-256 hash-chain
            $table->string('qr_verification_url');
            $table->date('issued_at');
            $table->date('expires_at');
            $table->integer('cpd_points_earned')->default(0);
            $table->string('status')->default('ACTIVE'); // ACTIVE, EXPIRED, REVOKED
            $table->timestamps();

            $table->index(['student_party_id', 'skill_competency_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_certificates');
        Schema::dropIfExists('edu_enrollments');
        Schema::dropIfExists('edu_cohorts');
        Schema::dropIfExists('edu_programs');
    }
};
