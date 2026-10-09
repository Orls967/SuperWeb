<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egov_regulatory_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_code')->unique();
            $table->string('regulatory_domain'); // TAX, LABOR_MANPOWER, ENVIRONMENT_AMDAL, SAFETY_K3
            $table->integer('template_version')->default(1); // 263.5
            $table->string('idempotency_key')->unique(); // 263.4
            $table->json('payload_data_json');
            $table->string('status')->default('PENDING'); // PENDING, SUBMITTED, FAILED_RETRYING, ACKNOWLEDGED
            $table->string('official_acknowledgement_receipt')->nullable(); // 263.7
            $table->integer('retry_count')->default(0); // 263.6
            $table->timestamps();
        });

        Schema::create('egov_operating_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_code')->unique();
            $table->string('business_line')->index();
            $table->string('permit_name');
            $table->date('expiry_date');
            $table->boolean('operation_blocked')->default(false); // 263.2, 263.4
            $table->string('renewal_status')->default('ACTIVE'); // ACTIVE, RENEWAL_PENDING, EXPIRED_BLOCKED
            $table->timestamps();
        });

        Schema::create('egov_public_disclosures', function (Blueprint $table) {
            $table->id();
            $table->string('disclosure_code')->unique();
            $table->string('disclosure_type'); // EMISSIONS_GHG, WORKFORCE_SAFETY, CSR_COMMUNITY
            $table->string('reporting_period');
            $table->decimal('published_metric_value', 15, 2);
            $table->decimal('internal_ledger_verified_value', 15, 2); // 263.3, 263.4
            $table->integer('version')->default(1);
            $table->boolean('is_verified_matching')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egov_public_disclosures');
        Schema::dropIfExists('egov_operating_licenses');
        Schema::dropIfExists('egov_regulatory_submissions');
    }
};
