<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 184.2: B2G public service contracts & tenant segregation
        Schema::create('dst_b2g_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->unique();
            $table->string('government_agency_tenant');
            $table->string('district_name');
            $table->decimal('contract_value_idr', 18, 2);
            $table->boolean('milestone_accepted')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
        });

        // 184.4: Community incident reporting channel with PII anonymization
        Schema::create('dst_community_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->string('district_code');
            $table->string('issue_category'); // POTHOLE, STREETLIGHT, WASTE
            $table->string('verified_location_gps');
            $table->string('reporter_raw_phone'); // Private PII
            $table->string('public_display_summary'); // Sanitized, no PII
            $table->integer('sla_hours')->default(24);
            $table->string('status')->default('OPEN'); // OPEN, RESOLVED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dst_community_reports');
        Schema::dropIfExists('dst_b2g_contracts');
    }
};
