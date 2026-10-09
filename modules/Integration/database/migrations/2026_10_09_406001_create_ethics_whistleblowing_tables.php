<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_ethics_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_code')->unique();
            $table->string('channel'); // web, mobile, phone
            $table->string('category'); // corruption, harassment, safety, retaliation
            $table->text('allegation_encrypted');
            $table->boolean('is_anonymous')->default(true); // 406.5
            $table->string('whistleblower_pseudonym')->nullable(); // strictly pseudonymous
            $table->string('status')->default('triage'); // triage, investigating, closed
            $table->boolean('anti_retaliation_monitoring_active')->default(true); // 406.4, 406.5
            $table->integer('sla_days_remaining')->default(30);
            $table->boolean('escalated_to_committee')->default(false); // 406.6
            $table->timestamps();
        });

        Schema::create('gov_ethics_investigations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('gov_ethics_cases')->cascadeOnDelete();
            $table->string('lead_investigator');
            $table->text('investigation_findings')->nullable();
            $table->string('disciplinary_recommendation')->nullable();
            $table->boolean('systemic_action_closed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_ethics_investigations');
        Schema::dropIfExists('gov_ethics_cases');
    }
};
