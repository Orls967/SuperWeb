<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_regulatory_change_events', function (Blueprint $table) {
            $table->id();
            $table->string('regulation_code')->unique();
            $table->string('title');
            $table->boolean('is_immediate_emergency_enactment')->default(false); // 468.5 edge case
            $table->json('affected_lines'); // multi-line impact analysis (468.1)
            $table->boolean('controls_built_and_tested')->default(false); // 468.2, 468.4
            $table->boolean('compliance_gap_disclosed_to_auditor')->default(false); // 468.6 risk
            $table->string('status')->default('analyzing'); // analyzing, controls_built, compliant
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_regulatory_change_events');
    }
};
