<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_high_impact_safety_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_code')->unique();
            $table->string('model_identifier');
            $table->string('impact_tier'); // HIGH_IMPACT, LOW_IMPACT
            $table->boolean('has_approved_safety_case')->default(false); // 356.2 & 356.4
            $table->boolean('launch_permitted')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_model_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique();
            $table->string('model_identifier');
            $table->string('severity'); // HIGH, CRITICAL, LOW
            $table->boolean('affects_multiple_domains')->default(false);
            $table->boolean('cross_domain_war_room_activated')->default(false); // 356.5 Edge case
            $table->boolean('containment_rollback_executed')->default(false); // 356.1 & 356.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_incidents');
        Schema::dropIfExists('ai_high_impact_safety_cases');
    }
};
