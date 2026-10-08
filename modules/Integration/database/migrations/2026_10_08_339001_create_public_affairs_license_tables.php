<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_stakeholder_issue_statements', function (Blueprint $table) {
            $table->id();
            $table->string('issue_code')->unique();
            $table->string('line_code');
            $table->string('issue_severity'); // LOW, HIGH, CRITICAL_VIRAL
            $table->text('holding_statement_draft');
            $table->boolean('statement_approved_by_corpcomms')->default(false); // 339.2 & 339.4
            $table->boolean('crisis_comms_activated')->default(false); // 339.5 Edge case
            $table->boolean('statement_released')->default(false);
            $table->timestamps();
        });

        Schema::create('government_engagement_registers', function (Blueprint $table) {
            $table->id();
            $table->string('engagement_code')->unique();
            $table->string('government_agency');
            $table->string('official_name_and_title');
            $table->string('internal_representative_name');
            $table->text('meeting_purpose_topic');
            $table->boolean('conflict_of_interest_screened')->default(true); // 339.3 & 339.4
            $table->boolean('transparency_register_logged')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_engagement_registers');
        Schema::dropIfExists('public_stakeholder_issue_statements');
    }
};
