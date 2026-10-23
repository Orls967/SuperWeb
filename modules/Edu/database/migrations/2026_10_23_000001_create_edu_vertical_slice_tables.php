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
        Schema::create('edu_research_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('grant_code')->unique();
            $table->string('title');
            $table->string('principal_investigator_name');
            $table->string('institution_party_uuid');
            $table->bigInteger('total_grant_minor');
            $table->bigInteger('disbursed_grant_minor')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['status', 'institution_party_uuid']);
        });

        Schema::create('edu_grant_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('milestone_code')->unique();
            $table->string('grant_uuid');
            $table->string('milestone_title');
            $table->bigInteger('disbursement_amount_minor');
            $table->timestamp('disbursed_at')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, DISBURSED
            $table->timestamps();

            $table->index(['grant_uuid', 'status']);
        });

        Schema::create('edu_exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('attempt_code')->unique();
            $table->string('student_party_uuid');
            $table->string('enrollment_code');
            $table->string('exam_title');
            $table->integer('score');
            $table->integer('passing_score')->default(75);
            $table->boolean('is_passed')->default(false);
            $table->string('proctor_verification_hash');
            $table->string('status')->default('COMPLETED');
            $table->timestamps();

            $table->index(['student_party_uuid', 'status']);
        });

        Schema::create('edu_scholarship_escrows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('escrow_code')->unique();
            $table->string('sponsor_name');
            $table->bigInteger('total_escrow_minor');
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('disbursed_minor')->default(0);
            $table->string('status')->default('ACTIVE'); // ACTIVE, RECONCILED, CLOSED
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_scholarship_escrows');
        Schema::dropIfExists('edu_exam_attempts');
        Schema::dropIfExists('edu_grant_milestones');
        Schema::dropIfExists('edu_research_grants');
    }
};
