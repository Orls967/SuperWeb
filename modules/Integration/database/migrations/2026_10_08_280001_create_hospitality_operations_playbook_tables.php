<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_operations_sop_bible', function (Blueprint $table) {
            $table->id();
            $table->string('sop_code')->unique();
            $table->string('venue_type'); // RESTAURANT, HOTEL, CONVENTION_VENUE
            $table->string('title');
            $table->string('version')->default('V1.0');
            $table->boolean('is_active_version_immutable')->default(true); // 270.4
            $table->string('language_code', 5)->default('ID'); // 280.7
            $table->timestamps();
        });

        Schema::create('hospitality_worker_attestations', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id')->index();
            $table->string('sop_code')->index();
            $table->boolean('is_training_attested')->default(false); // 280.1 & 280.4
            $table->date('attestation_date');
            $table->timestamps();
        });

        Schema::create('hospitality_shift_playbooks', function (Blueprint $table) {
            $table->id();
            $table->string('shift_code')->unique();
            $table->string('outlet_code')->index();
            $table->string('shift_type'); // MORNING, EVENING, NIGHT
            $table->string('assigned_worker_id');
            $table->boolean('worker_authorized')->default(false); // 280.4
            $table->boolean('completion_evidence_submitted')->default(false);
            $table->timestamps();
        });

        Schema::create('hospitality_mystery_guest_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_code')->unique();
            $table->string('outlet_code')->index();
            $table->decimal('service_score', 5, 2);
            $table->decimal('cleanliness_score', 5, 2);
            $table->decimal('food_quality_score', 5, 2);
            $table->decimal('composite_score', 5, 2);
            $table->string('outlet_grade'); // A, B, C, D
            $table->boolean('action_plan_required')->default(false); // 280.5 Edge Case
            $table->boolean('brand_scorecard_impacted')->default(false); // 280.5
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_mystery_guest_audits');
        Schema::dropIfExists('hospitality_shift_playbooks');
        Schema::dropIfExists('hospitality_worker_attestations');
        Schema::dropIfExists('hospitality_operations_sop_bible');
    }
};
