<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_data_processing_proofs', function (Blueprint $table) {
            $table->id();
            $table->string('processing_job_code')->unique();
            $table->string('subject_id');
            $table->string('purpose_scope'); // ANALYTICS, DIRECT_MARKETING, CROSS_BORDER
            $table->boolean('has_valid_consent_proof')->default(false); // 341.2, 341.4, 341.6 Fail-closed
            $table->boolean('processing_blocked')->default(false);
            $table->timestamps();
        });

        Schema::create('privacy_consent_revocation_events', function (Blueprint $table) {
            $table->id();
            $table->string('revocation_code')->unique();
            $table->string('subject_id');
            $table->string('purpose_scope');
            $table->decimal('propagation_latency_seconds', 8, 2);
            $table->decimal('sla_threshold_seconds', 8, 2)->default(30.00); // 341.4 & 341.5
            $table->boolean('sla_breached')->default(false);
            $table->boolean('downstream_notified')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_consent_revocation_events');
        Schema::dropIfExists('privacy_data_processing_proofs');
    }
};
