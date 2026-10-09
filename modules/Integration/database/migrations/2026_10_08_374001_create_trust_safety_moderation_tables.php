<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_safety_incident_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->string('reporter_anonymized_hash'); // 374.2, 374.4, 374.5 Edge case
            $table->boolean('reporter_identity_redacted')->default(true);
            $table->string('risk_tier'); // URGENT, HIGH, MEDIUM, LOW
            $table->integer('sla_minutes')->default(15);
            $table->boolean('sla_met')->default(true); // 374.4
            $table->timestamps();
        });

        Schema::create('trust_safety_moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_code')->unique();
            $table->string('target_content_id');
            $table->string('decision_type'); // TAKEDOWN, RESTRICT, WARN
            $table->boolean('appeal_permitted')->default(true); // 374.2 & 374.4
            $table->boolean('appeal_lodged')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_safety_moderation_actions');
        Schema::dropIfExists('trust_safety_incident_reports');
    }
};
