<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_security_penetration_findings', function (Blueprint $table) {
            $table->id();
            $table->string('finding_code')->unique();
            $table->string('severity'); // CRITICAL, HIGH, MEDIUM, LOW
            $table->boolean('release_blocked')->default(false); // 398.4 & 398.5 Edge case
            $table->boolean('resolved_and_retested')->default(false);
            $table->timestamps();
        });

        Schema::create('global_stress_privacy_scan_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_run_code')->unique();
            $table->boolean('pii_detected_in_traces')->default(false);
            $table->boolean('privacy_passed')->default(true); // 398.3 & 398.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_privacy_scan_audits');
        Schema::dropIfExists('global_stress_security_penetration_findings');
    }
};
