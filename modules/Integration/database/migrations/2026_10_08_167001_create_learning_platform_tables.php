<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 167.1: Courses & versions
        Schema::create('camp_courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_code')->unique();
            $table->string('title');
            $table->integer('version')->default(1);
            $table->boolean('is_immutable_snapshot')->default(false);
            $table->string('prerequisite_course_code')->nullable();
            $table->timestamps();
        });

        // 167.2: Question banks & assessments
        Schema::create('camp_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessment_code')->unique();
            $table->string('course_code');
            $table->integer('seed_number');
            $table->json('randomized_form_data');
            $table->timestamps();
        });

        // 167.3: Digital credentials & QR verification
        Schema::create('camp_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('credential_code')->unique();
            $table->unsignedBigInteger('learner_id');
            $table->string('course_code');
            $table->string('qr_verification_hash');
            $table->boolean('is_revoked')->default(false);
            $table->string('superseded_by_code')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['learner_id', 'course_code']); // Idempotent completion
        });

        // 167.5: Offline learning progress sync
        Schema::create('camp_offline_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_key')->unique();
            $table->unsignedBigInteger('learner_id');
            $table->string('course_code');
            $table->integer('progress_pct')->default(0);
            $table->string('status')->default('SYNCED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camp_offline_syncs');
        Schema::dropIfExists('camp_credentials');
        Schema::dropIfExists('camp_assessments');
        Schema::dropIfExists('camp_courses');
    }
};
