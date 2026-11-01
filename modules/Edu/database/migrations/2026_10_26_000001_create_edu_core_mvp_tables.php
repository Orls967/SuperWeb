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
        // 1. Enhance edu_programs
        Schema::table('edu_programs', function (Blueprint $table) {
            $table->integer('passing_score')->default(75);
            $table->integer('validity_months')->default(24);
        });

        // 2. Create edu_prerequisites (Anti-Cycle DAG)
        Schema::create('edu_prerequisites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('program_id');
            $table->string('prerequisite_program_id');
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('edu_programs')->cascadeOnDelete();
            $table->foreign('prerequisite_program_id')->references('id')->on('edu_programs')->cascadeOnDelete();
            $table->unique(['program_id', 'prerequisite_program_id']);
        });

        // 3. Create edu_corporate_quotas (B2B Prepaid Quotas)
        Schema::create('edu_corporate_quotas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('corporate_client_id');
            $table->string('program_id');
            $table->integer('quota_total');
            $table->integer('quota_used')->default(0);
            $table->bigInteger('unit_price_minor');
            $table->string('status')->default('ACTIVE');
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('edu_programs')->cascadeOnDelete();
            $table->index(['corporate_client_id', 'status']);
        });

        // 4. Enhance edu_enrollments
        Schema::table('edu_enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('student_user_id')->nullable();
            $table->string('corporate_quota_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('prerequisite_waiver_reason')->nullable();

            $table->foreign('student_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('corporate_quota_id')->references('id')->on('edu_corporate_quotas')->nullOnDelete();
        });

        // 5. Enhance edu_exam_attempts
        Schema::table('edu_exam_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('student_user_id')->nullable();
            $table->string('enrollment_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();

            $table->foreign('student_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('enrollment_id')->references('id')->on('edu_enrollments')->cascadeOnDelete();
        });

        // 6. Create edu_certificate_sequences (Gapless Sequential Numbering Lock)
        Schema::create('edu_certificate_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('program_id');
            $table->integer('year_period');
            $table->bigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('edu_programs')->cascadeOnDelete();
            $table->unique(['program_id', 'year_period']);
        });

        // 7. Enhance edu_certificates
        Schema::table('edu_certificates', function (Blueprint $table) {
            $table->unsignedBigInteger('student_user_id')->nullable();
            $table->string('exam_attempt_id')->nullable();
            $table->string('prev_hash', 64)->default(str_repeat('0', 64));
            $table->string('qr_token')->nullable();
            $table->string('idempotency_key')->nullable()->unique();

            $table->foreign('student_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('exam_attempt_id')->references('id')->on('edu_exam_attempts')->cascadeOnDelete();
        });

        // 8. Enhance edu_job_openings
        Schema::table('edu_job_openings', function (Blueprint $table) {
            $table->string('opening_code')->nullable()->unique();
            $table->string('hiring_entity')->nullable();
            $table->integer('capacity')->default(1);
            $table->bigInteger('annual_salary_minor')->nullable();
            $table->integer('headhunter_fee_bps')->default(1500);
            $table->string('required_credential_code')->nullable();
        });

        // 9. Create edu_talent_preferences
        Schema::create('edu_talent_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('student_user_id');
            $table->string('opening_id');
            $table->integer('rank_order');
            $table->timestamps();

            $table->foreign('student_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('opening_id')->references('id')->on('edu_job_openings')->cascadeOnDelete();
            $table->unique(['student_user_id', 'opening_id']);
        });

        // 10. Create edu_placements
        Schema::create('edu_placements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('placement_code')->unique();
            $table->unsignedBigInteger('student_user_id');
            $table->string('opening_id');
            $table->date('placed_at');
            $table->integer('guarantee_days')->default(90);
            $table->string('status')->default('PLACED');
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->foreign('student_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('opening_id')->references('id')->on('edu_job_openings')->cascadeOnDelete();
        });

        // 11. Create edu_headhunter_fees
        Schema::create('edu_headhunter_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('placement_id');
            $table->bigInteger('fee_minor');
            $table->string('status')->default('HELD');
            $table->timestamp('held_at');
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->foreign('placement_id')->references('id')->on('edu_placements')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_headhunter_fees');
        Schema::dropIfExists('edu_placements');
        Schema::dropIfExists('edu_talent_preferences');

        Schema::table('edu_job_openings', function (Blueprint $table) {
            $table->dropColumn([
                'opening_code',
                'hiring_entity',
                'capacity',
                'annual_salary_minor',
                'headhunter_fee_bps',
                'required_credential_code',
            ]);
        });

        Schema::table('edu_certificates', function (Blueprint $table) {
            $table->dropForeign(['student_user_id']);
            $table->dropForeign(['exam_attempt_id']);
            $table->dropColumn([
                'student_user_id',
                'exam_attempt_id',
                'prev_hash',
                'qr_token',
                'idempotency_key',
            ]);
        });

        Schema::dropIfExists('edu_certificate_sequences');

        Schema::table('edu_exam_attempts', function (Blueprint $table) {
            $table->dropForeign(['student_user_id']);
            $table->dropForeign(['enrollment_id']);
            $table->dropColumn([
                'student_user_id',
                'enrollment_id',
                'idempotency_key',
            ]);
        });

        Schema::table('edu_enrollments', function (Blueprint $table) {
            $table->dropForeign(['student_user_id']);
            $table->dropForeign(['corporate_quota_id']);
            $table->dropColumn([
                'student_user_id',
                'corporate_quota_id',
                'idempotency_key',
                'prerequisite_waiver_reason',
            ]);
        });

        Schema::dropIfExists('edu_corporate_quotas');
        Schema::dropIfExists('edu_prerequisites');

        Schema::table('edu_programs', function (Blueprint $table) {
            $table->dropColumn(['passing_score', 'validity_months']);
        });
    }
};
